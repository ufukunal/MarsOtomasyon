<?php

namespace App\Actions\Returns;

use App\Actions\Documents\CalculateDocumentTotals;
use App\Actions\Numbering\GenerateDocumentNumber;
use App\Actions\Periods\EnsurePeriodOpen;
use App\Actions\Stock\ReceiveToQuarantine;
use App\Actions\Stock\RecordStockMovement;
use App\DataObjects\QuarantineReceiptData;
use App\DataObjects\StockMovementData;
use App\Enums\DocumentType;
use App\Models\Period\ContactTransaction;
use App\Models\Period\Document;
use App\Models\Period\DocumentLine;
use App\Models\Period\DocumentRelation;
use App\Support\Audit\AuditContext;
use App\Support\Auth\MutationAuthorizer;
use App\Support\Concurrency\IdempotencyKey;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

final class PostReturnDocument
{
    public function __construct(
        private readonly CalculateDocumentTotals $calculator,
        private readonly ReturnLineAvailability $availability,
        private readonly ResolveSalesReturnUnitCost $salesReturnCost,
        private readonly EnsurePeriodOpen $ensurePeriodOpen,
        private readonly GenerateDocumentNumber $numbers,
        private readonly ReceiveToQuarantine $receiveToQuarantine,
        private readonly RecordStockMovement $recordStockMovement,
    ) {}

    public function handle(Document $return, string $idempotencyKey): Document
    {
        MutationAuthorizer::authorize('returns.update');

        $id = IdempotencyKey::run(
            $idempotencyKey,
            'return.post:'.$return->id,
            fn (): int => DB::connection('period')->transaction(function () use ($return, $idempotencyKey): int {
                $locked = Document::query()->with('lines')->lockForUpdate()->findOrFail($return->id);

                if (! in_array($locked->document_type, [DocumentType::SalesReturn, DocumentType::PurchaseReturn], true)
                    || $locked->status !== 'draft') {
                    throw new DomainException('Yalnız taslak iade belgesi kesinleştirilebilir.');
                }

                $this->ensurePeriodOpen->handle(CarbonImmutable::parse($locked->document_date));
                $this->assertTotals($locked);
                $this->assertSourceAvailability($locked);

                if ($locked->number === null) {
                    $locked->number = $this->numbers->handle(
                        $locked->document_type->value,
                        $locked->document_date->year,
                    );
                }

                $actor = auth()->user();

                foreach ($locked->lines as $line) {
                    if ($line->line_kind !== 'stock') {
                        continue;
                    }

                    if ($line->location_id === null || $line->base_quantity === null) {
                        throw new DomainException('Stok iade satırında lokasyon ve temel miktar zorunludur.');
                    }

                    $sourceLine = DocumentLine::query()->findOrFail((int) $line->source_line_id);

                    if ($locked->document_type === DocumentType::SalesReturn) {
                        $this->receiveToQuarantine->handle(
                            new QuarantineReceiptData(
                                productId: (int) $line->product_id,
                                locationId: (int) $line->location_id,
                                movementDate: $locked->document_date->toDateString(),
                                quantity: (string) $line->base_quantity,
                                unitCost: $this->salesReturnCost->handle($sourceLine),
                                sourceDocumentType: DocumentType::SalesReturn->value,
                                sourceDocumentId: (int) $locked->id,
                                sourceLineId: (int) $line->id,
                                documentNo: $locked->number,
                                note: $locked->notes,
                                actorUserId: $actor?->id,
                                actorUserName: $actor?->name,
                            ),
                            hash('sha256', $idempotencyKey.':quarantine:'.$line->id),
                        );
                    } else {
                        $this->recordStockMovement->handle(new StockMovementData(
                            productId: (int) $line->product_id,
                            locationId: (int) $line->location_id,
                            movementDate: $locked->document_date->toDateString(),
                            direction: 'out',
                            reason: DocumentType::PurchaseReturn->value,
                            quantity: (string) $line->base_quantity,
                            updatesAverage: false,
                            documentType: DocumentType::PurchaseReturn->value,
                            documentId: (int) $locked->id,
                            documentNo: $locked->number,
                            note: $locked->notes,
                            actorUserId: $actor?->id,
                            actorUserName: $actor?->name,
                        ));
                    }
                }

                if ($locked->contact_id === null) {
                    throw new DomainException('İade belgesinde cari zorunludur.');
                }

                if ($locked->document_type === DocumentType::SalesReturn && $locked->currency !== 'TRY') {
                    throw new DomainException('Satış iadesi yalnız TRY satış faturasından oluşturulabilir.');
                }

                $contactAmount = $locked->document_type === DocumentType::PurchaseReturn
                    ? bcadd(bcmul((string) $locked->grand_total, (string) $locked->exchange_rate, 8), '0', 4)
                    : (string) $locked->grand_total;

                ContactTransaction::query()->create([
                    'contact_id' => $locked->contact_id,
                    'document_id' => $locked->id,
                    'transaction_type' => $locked->document_type->value,
                    'direction' => $locked->document_type === DocumentType::SalesReturn ? 'credit' : 'debit',
                    'transaction_date' => $locked->document_date,
                    'amount' => $contactAmount,
                    'currency' => 'TRY',
                    'description' => $locked->notes,
                    'created_by' => $actor?->id,
                    'created_by_name' => $actor?->name,
                ]);

                $locked->status = 'posted';
                $locked->posted_by = $actor?->id;
                $locked->posted_by_name = $actor?->name;
                $locked->posted_at = now();
                $locked->version = (int) $locked->version + 1;
                $locked->save();

                AuditContext::period(
                    'İade belgesi kesinleştirildi.',
                    [
                        'return_document_id' => $locked->id,
                        'type' => $locked->document_type->value,
                        'number' => $locked->number,
                        'financial_effect' => $contactAmount,
                    ],
                    $locked,
                    'return_posted',
                );

                return (int) $locked->id;
            }, attempts: 3),
        );

        return Document::query()->with('lines')->findOrFail((int) $id);
    }

    private function assertTotals(Document $document): void
    {
        $totals = $this->calculator->handle(
            $document->lines->map(fn ($line) => [
                'quantity' => (string) $line->quantity,
                'unit_price' => (string) $line->unit_price,
                'line_discount_rate' => (string) $line->line_discount_rate,
                'line_discount_amount' => (string) $line->line_discount_amount,
                'vat_rate' => (string) $line->vat_rate,
            ])->all(),
            (string) $document->discount_rate,
            (string) $document->discount_amount,
        );

        foreach ($document->lines->values() as $index => $line) {
            $expected = $totals->lines[$index];

            if (bccomp((string) $line->line_total, $expected->lineTotal, 4) !== 0) {
                throw new DomainException('İade satır toplamı hesap motoruyla eşleşmiyor.');
            }

            if ($line->line_kind === 'stock') {
                $base = bcadd(
                    bcmul((string) $line->quantity, (string) $line->conversion_factor, 6),
                    '0',
                    3,
                );

                if (bccomp((string) $line->base_quantity, $base, 3) !== 0) {
                    throw new DomainException('İade temel miktar snapshotı geçersiz.');
                }
            }
        }

        foreach ([
            'discount_amount' => $totals->discountAmount,
            'subtotal' => $totals->subtotal,
            'tax_base' => $totals->taxBase,
            'vat_amount' => $totals->vatAmount,
            'rounding_difference' => $totals->roundingDifference,
            'grand_total' => $totals->grandTotal,
        ] as $field => $expected) {
            if (bccomp((string) $document->getAttribute($field), $expected, 4) !== 0) {
                throw new DomainException("İade {$field} toplamı hesap motoruyla eşleşmiyor.");
            }
        }
    }

    private function assertSourceAvailability(Document $document): void
    {
        $expectedSourceType = $document->document_type === DocumentType::SalesReturn
            ? DocumentType::SalesInvoice
            : DocumentType::SupplierInvoice;

        foreach ($document->lines as $line) {
            if ($line->source_line_id === null) {
                throw new DomainException('İade satırında kaynak fatura satırı zorunludur.');
            }

            $source = DocumentLine::query()
                ->with('document')
                ->lockForUpdate()
                ->findOrFail((int) $line->source_line_id);

            if ($source->document->document_type !== $expectedSourceType
                || $source->document->status !== 'posted'
                || (int) $source->document->contact_id !== (int) $document->contact_id
                || $source->document->currency !== $document->currency
                || $line->line_kind !== $source->line_kind
                || (int) ($line->product_id ?? 0) !== (int) ($source->product_id ?? 0)
                || (int) ($line->unit_id ?? 0) !== (int) ($source->unit_id ?? 0)) {
                throw new DomainException('İade kaynak fatura satırı snapshotıyla eşleşmiyor.');
            }

            if (DocumentRelation::query()
                ->where('relation_type', 'reversal_of')
                ->where('target_document_id', $source->document_id)
                ->exists()) {
                throw new DomainException('Terslenmiş fatura iade kaynağı olamaz.');
            }

            $remaining = $this->availability->remaining($source, $document->document_type);

            if (bccomp((string) $line->quantity, $remaining, 3) > 0) {
                throw new DomainException('İade miktarı kaynak satır kalanını aşıyor.');
            }
        }
    }
}

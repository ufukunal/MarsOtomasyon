<?php

namespace App\Actions\Documents;

use App\Actions\Numbering\GenerateDocumentNumber;
use App\Actions\Periods\EnsurePeriodOpen;
use App\Actions\Production\ReverseProductionServiceInvoiceCosts;
use App\Actions\Purchases\ReverseSupplierInvoiceCosts;
use App\Actions\Stock\RecordStockMovement;
use App\DataObjects\StockMovementData;
use App\Enums\DocumentType;
use App\Exceptions\Documents\AlreadyReversedException;
use App\Models\Period\BankMovement;
use App\Models\Period\CashMovement;
use App\Models\Period\ContactTransaction;
use App\Models\Period\Document;
use App\Models\Period\DocumentLine;
use App\Models\Period\DocumentRelation;
use App\Models\Period\StockMovement;
use App\Support\Audit\AuditContext;
use App\Support\Auth\MutationAuthorizer;
use App\Support\Concurrency\IdempotencyKey;
use Carbon\CarbonImmutable;
use DomainException;

final class ReverseDocument
{
    public function __construct(
        private readonly EnsurePeriodOpen $ensurePeriodOpen,
        private readonly GenerateDocumentNumber $numbers,
        private readonly RecordStockMovement $recordStockMovement,
        private readonly ResolveSourceLineage $lineage,
        private readonly ReverseSupplierInvoiceCosts $reversePurchaseCosts,
        private readonly ReverseProductionServiceInvoiceCosts $reverseProductionServiceCosts,
        private readonly VerifyReversal $verify,
    ) {}

    public function handle(
        Document $original,
        string $reversalDate,
        string $reason,
        string $idempotencyKey,
    ): Document {
        MutationAuthorizer::authorize($this->permission($original));
        $reason = trim($reason);

        if ($reason === '') {
            throw new DomainException('Ters kayıt gerekçesi zorunludur.');
        }

        return IdempotencyKey::run(
            $idempotencyKey,
            'document.reverse:'.$original->id,
            function () use ($original, $reversalDate, $reason): Document {
                $locked = Document::query()->with('lines')->lockForUpdate()->findOrFail($original->id);

                if ($locked->status !== 'posted'
                    || ! in_array($locked->document_type, [
                        DocumentType::Dispatch,
                        DocumentType::SalesInvoice,
                        DocumentType::Collection,
                        DocumentType::ContactDebitCredit,
                        DocumentType::GoodsReceipt,
                        DocumentType::SupplierInvoice,
                        DocumentType::Payment,
                        DocumentType::Expense,
                        DocumentType::Advance,
                        DocumentType::AdvanceReturn,
                    ], true)) {
                    throw new DomainException('Bu belge ters kayıt için uygun değil.');
                }

                if (DocumentRelation::query()
                    ->where('relation_type', 'reversal_of')
                    ->where('target_document_id', $locked->id)
                    ->exists()) {
                    throw new AlreadyReversedException('Belge daha önce terslenmiş.');
                }

                if ($locked->document_type === DocumentType::Dispatch) {
                    $this->assertDispatchHasNoActiveInvoice($locked);
                }

                if ($locked->document_type === DocumentType::GoodsReceipt) {
                    $this->assertGoodsReceiptHasNoActiveInvoice($locked);
                }

                $date = CarbonImmutable::parse($reversalDate);
                $this->ensurePeriodOpen->handle($date);
                $actor = auth()->user();

                $reversal = Document::query()->create([
                    'document_type' => $locked->document_type->value,
                    'number' => $this->numbers->handle($locked->document_type->value, $date->year),
                    'revision_no' => 0,
                    'document_date' => $date->toDateString(),
                    'due_date' => $locked->due_date,
                    'valid_until' => $locked->valid_until,
                    'contact_id' => $locked->contact_id,
                    'currency' => $locked->currency,
                    'exchange_rate' => $locked->exchange_rate,
                    'status' => 'draft',
                    'discount_rate' => $locked->discount_rate,
                    'discount_amount' => $locked->discount_amount,
                    'subtotal' => $locked->subtotal,
                    'tax_base' => $locked->tax_base,
                    'vat_amount' => $locked->vat_amount,
                    'rounding_difference' => $locked->rounding_difference,
                    'grand_total' => $locked->grand_total,
                    'requirements_snapshot' => $locked->requirements_snapshot,
                    'notes' => $reason,
                    'created_by' => $actor?->id,
                    'created_by_name' => $actor?->name,
                 ]);

                foreach ($locked->lines as $line) {
                    DocumentLine::query()->create([
                        ...$line->only([
                            'line_no', 'line_kind', 'product_id', 'description', 'unit_id', 'quantity',
                            'conversion_factor', 'base_quantity', 'location_id', 'unit_price',
                            'line_discount_rate', 'line_discount_amount', 'vat_rate', 'line_total',
                            'reserve_stock', 'configuration',
                        ]),
                        'document_id' => $reversal->id,
                        'cancelled_quantity' => '0.000',
                        'source_line_id' => null,
                    ]);
                }

                DocumentRelation::query()->create([
                    'source_document_id' => $reversal->id,
                    'target_document_id' => $locked->id,
                    'relation_type' => 'reversal_of',
                    'created_by' => $actor?->id,
                    'created_by_name' => $actor?->name,
                ]);

                $this->reverseStock($locked, $reversal);
                $this->reverseContact($locked, $reversal, $actor?->id, $actor?->name);
                $this->reverseFinance($locked, $reversal, $actor?->id, $actor?->name);
                $this->reversePurchaseCosts->handle($locked);
                $this->reverseProductionServiceCosts->handle(
                    $locked,
                    $reversal->document_date->toDateString(),
                );

                $reversal->status = 'posted';
                $reversal->posted_by = $actor?->id;
                $reversal->posted_by_name = $actor?->name;
                $reversal->posted_at = now();
                $reversal->version = (int) $reversal->version + 1;
                $reversal->save();

                $this->verify->handle($locked, $reversal);

                AuditContext::period(
                    'Belge ters kayıtla düzeltildi.',
                    [
                        'original_document_id' => $locked->id,
                        'reversal_document_id' => $reversal->id,
                        'reason' => $reason,
                    ],
                    $reversal,
                    'document_reversed',
                );

                return $reversal->load('lines');
            },
        );
    }

    private function permission(Document $document): string
    {
        return match ($document->document_type) {
            DocumentType::Dispatch => 'dispatches.cancel',
            DocumentType::SalesInvoice => 'sales_invoices.cancel',
            DocumentType::Collection => 'collections.cancel',
            DocumentType::GoodsReceipt => 'goods_receipts.cancel',
            DocumentType::SupplierInvoice => 'supplier_invoices.cancel',
            DocumentType::Payment => 'payments.cancel',
            DocumentType::Expense => 'expenses.cancel',
            DocumentType::Advance,
            DocumentType::AdvanceReturn => 'advances.cancel',
            DocumentType::ContactDebitCredit => 'contacts.update',
            default => 'documents.cancel',
        };
    }

    private function assertDispatchHasNoActiveInvoice(Document $dispatch): void
    {
        $invoiceLines = DocumentLine::query()
            ->with('document')
            ->whereHas('document', fn ($query) => $query
                ->where('document_type', DocumentType::SalesInvoice->value)
                ->where('status', 'posted'))
            ->whereNotNull('source_line_id')
            ->get();

        $dispatchLineIds = $dispatch->lines
            ->pluck('id')
            ->map(fn ($id): int => (int) $id);

        foreach ($invoiceLines as $invoiceLine) {
            if (DocumentRelation::query()
                ->where('relation_type', 'reversal_of')
                ->where('target_document_id', $invoiceLine->document_id)
                ->exists()) {
                continue;
            }

            $lineage = $this->lineage->handle($invoiceLine);

            if ($dispatchLineIds->intersect($lineage['line_ids'])->isNotEmpty()) {
                throw new DomainException('Aktif faturası bulunan irsaliye önce terslenemez.');
            }
        }
    }

    private function assertGoodsReceiptHasNoActiveInvoice(Document $receipt): void
    {
        $receiptLineIds = $receipt->lines
            ->pluck('id')
            ->map(fn ($id): int => (int) $id);

        $invoiceLines = DocumentLine::query()
            ->with('document')
            ->whereIn('source_line_id', $receiptLineIds)
            ->whereHas('document', fn ($query) => $query
                ->where('document_type', DocumentType::SupplierInvoice->value)
                ->where('status', 'posted'))
            ->get();

        foreach ($invoiceLines as $invoiceLine) {
            if (DocumentRelation::query()
                ->where('relation_type', 'reversal_of')
                ->where('target_document_id', $invoiceLine->document_id)
                ->exists()) {
                continue;
            }

            throw new DomainException('Aktif alış faturası bulunan mal kabul önce terslenemez.');
        }
    }

    private function reverseStock(Document $original, Document $reversal): void
    {
        $actor = auth()->user();

        $movements = StockMovement::query()
            ->where('document_type', $original->document_type->value)
            ->where('document_id', $original->id)
            ->orderBy('id')
            ->get();

        foreach ($movements as $movement) {
            $this->recordStockMovement->handle(new StockMovementData(
                productId: (int) $movement->product_id,
                locationId: (int) $movement->location_id,
                movementDate: $reversal->document_date->toDateString(),
                direction: $movement->direction === 'out' ? 'in' : 'out',
                reason: 'reversal',
                quantity: (string) $movement->quantity,
                unitCost: (string) $movement->unit_cost,
                updatesAverage: false,
                documentType: $reversal->document_type->value,
                documentId: (int) $reversal->id,
                documentNo: $reversal->number,
                note: $reversal->notes,
                actorUserId: $actor?->id,
                actorUserName: $actor?->name,
            ));
        }
    }

    private function reverseContact(
        Document $original,
        Document $reversal,
        ?int $actorId,
        ?string $actorName,
    ): void {
        $movement = ContactTransaction::query()->where('document_id', $original->id)->first();

        if (! $movement) {
            return;
        }

        ContactTransaction::query()->create([
            'contact_id' => $movement->contact_id,
            'document_id' => $reversal->id,
            'transaction_type' => $movement->transaction_type,
            'direction' => $movement->direction === 'debit' ? 'credit' : 'debit',
            'transaction_date' => $reversal->document_date,
            'due_date' => $reversal->due_date,
            'amount' => $movement->amount,
            'currency' => $movement->currency,
            'reversal_of_id' => $movement->id,
            'description' => $reversal->notes,
            'created_by' => $actorId,
            'created_by_name' => $actorName,
        ]);
    }

    private function reverseFinance(
        Document $original,
        Document $reversal,
        ?int $actorId,
        ?string $actorName,
    ): void {
        $cash = CashMovement::query()->where('document_id', $original->id)->first();

        if ($cash) {
            CashMovement::query()->create([
                'cash_account_id' => $cash->cash_account_id,
                'document_id' => $reversal->id,
                'contact_id' => $cash->contact_id,
                'movement_date' => $reversal->document_date,
                'direction' => $cash->direction === 'in' ? 'out' : 'in',
                'movement_type' => 'reversal',
                'amount' => $cash->amount,
                'reference' => $reversal->number,
                'reversal_of_id' => $cash->id,
                'description' => $reversal->notes,
                'metadata' => $cash->metadata,
                'created_by' => $actorId,
                'created_by_name' => $actorName,
            ]);

            return;
        }

        $bank = BankMovement::query()->where('document_id', $original->id)->first();

        if ($bank) {
            BankMovement::query()->create([
                'bank_account_id' => $bank->bank_account_id,
                'document_id' => $reversal->id,
                'contact_id' => $bank->contact_id,
                'movement_date' => $reversal->document_date,
                'direction' => $bank->direction === 'in' ? 'out' : 'in',
                'movement_type' => 'reversal',
                'amount' => $bank->amount,
                'origin' => 'book',
                'reference' => $reversal->number,
                'reversal_of_id' => $bank->id,
                'description' => $reversal->notes,
                'metadata' => $bank->metadata,
                'created_by' => $actorId,
                'created_by_name' => $actorName,
            ]);
        }
    }
}

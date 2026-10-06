<?php

namespace App\Actions\Returns;

use App\Actions\Numbering\GenerateDocumentNumber;
use App\Actions\Periods\EnsurePeriodOpen;
use App\Actions\Stock\AdjustQuarantineBalance;
use App\Actions\Stock\RecordStockMovement;
use App\DataObjects\StockMovementData;
use App\Enums\DocumentType;
use App\Exceptions\Documents\AlreadyReversedException;
use App\Models\Period\ContactTransaction;
use App\Models\Period\Document;
use App\Models\Period\DocumentLine;
use App\Models\Period\DocumentRelation;
use App\Models\Period\QuarantineEntry;
use App\Models\Period\StockMovement;
use App\Support\Audit\AuditContext;
use App\Support\Auth\MutationAuthorizer;
use App\Support\Concurrency\IdempotencyKey;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

final class ReverseReturnDocument
{
    public function __construct(
        private readonly EnsurePeriodOpen $ensurePeriodOpen,
        private readonly GenerateDocumentNumber $numbers,
        private readonly AdjustQuarantineBalance $adjustQuarantine,
        private readonly RecordStockMovement $recordStockMovement,
    ) {}

    public function handle(
        Document $original,
        string $reversalDate,
        string $reason,
        string $idempotencyKey,
    ): Document {
        MutationAuthorizer::authorize('returns.cancel');
        $reason = trim($reason);

        if ($reason === '') {
            throw new DomainException('İade ters kayıt gerekçesi zorunludur.');
        }

        $id = IdempotencyKey::run(
            $idempotencyKey,
            'return.reverse:'.$original->id,
            fn (): int => DB::connection('period')->transaction(function () use (
                $original,
                $reversalDate,
                $reason,
            ): int {
                $locked = Document::query()->with('lines')->lockForUpdate()->findOrFail($original->id);

                if (! in_array($locked->document_type, [DocumentType::SalesReturn, DocumentType::PurchaseReturn], true)
                    || $locked->status !== 'posted') {
                    throw new DomainException('Bu belge iade ters kaydı için uygun değil.');
                }

                if (DocumentRelation::query()
                    ->where('relation_type', 'reversal_of')
                    ->where('target_document_id', $locked->id)
                    ->exists()) {
                    throw new AlreadyReversedException('İade belgesi daha önce terslenmiş.');
                }

                $date = CarbonImmutable::parse($reversalDate);
                $this->ensurePeriodOpen->handle($date);
                $actor = auth()->user();

                if ($locked->document_type === DocumentType::SalesReturn) {
                    $this->assertAndReverseQuarantine($locked);
                }

                $reversal = Document::query()->create([
                    'document_type' => $locked->document_type->value,
                    'number' => $this->numbers->handle($locked->document_type->value, $date->year),
                    'revision_no' => 0,
                    'document_date' => $date->toDateString(),
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

                $transaction = ContactTransaction::query()
                    ->where('document_id', $locked->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                ContactTransaction::query()->create([
                    'contact_id' => $transaction->contact_id,
                    'document_id' => $reversal->id,
                    'transaction_type' => $transaction->transaction_type,
                    'direction' => $transaction->direction === 'debit' ? 'credit' : 'debit',
                    'transaction_date' => $reversal->document_date,
                    'amount' => $transaction->amount,
                    'currency' => $transaction->currency,
                    'reversal_of_id' => $transaction->id,
                    'description' => $reason,
                    'created_by' => $actor?->id,
                    'created_by_name' => $actor?->name,
                ]);

                $reversal->status = 'posted';
                $reversal->posted_by = $actor?->id;
                $reversal->posted_by_name = $actor?->name;
                $reversal->posted_at = now();
                $reversal->version = (int) $reversal->version + 1;
                $reversal->save();

                AuditContext::period(
                    'İade belgesi ters kayıtla düzeltildi.',
                    [
                        'original_return_id' => $locked->id,
                        'reversal_return_id' => $reversal->id,
                        'reason' => $reason,
                    ],
                    $reversal,
                    'return_reversed',
                );

                return (int) $reversal->id;
            }, attempts: 3),
        );

        return Document::query()->with('lines')->findOrFail((int) $id);
    }

    private function assertAndReverseQuarantine(Document $return): void
    {
        $entries = QuarantineEntry::query()
            ->where('source_document_type', DocumentType::SalesReturn->value)
            ->where('source_document_id', $return->id)
            ->lockForUpdate()
            ->get();

        $stockLines = $return->lines->where('line_kind', 'stock');

        if ($entries->count() !== $stockLines->count()) {
            throw new DomainException('Satış iadesi karantina zinciri eksik.');
        }

        foreach ($entries as $entry) {
            if (bccomp((string) $entry->released_quantity, '0', 3) !== 0
                || bccomp((string) $entry->scrapped_quantity, '0', 3) !== 0
                || bccomp((string) $entry->reversed_quantity, '0', 3) !== 0) {
                throw new DomainException('Karantina kararı verilmiş satış iadesi doğrudan terslenemez.');
            }

            $this->adjustQuarantine->handle(
                (int) $entry->product_id,
                (int) $entry->location_id,
                bcmul((string) $entry->quantity, '-1', 3),
            );

            $entry->setAttribute('reversed_quantity', (string) $entry->quantity);
            $entry->status = 'reversed';
            $entry->reversed_at = now();
            $entry->save();
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
}

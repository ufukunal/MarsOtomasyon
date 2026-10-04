<?php

namespace App\Actions\Purchases;

use App\Actions\Stock\UpdateMovingAverage;
use App\Enums\DocumentType;
use App\Models\Period\Document;
use App\Models\Period\PurchaseMatch;
use DomainException;

final class ReverseSupplierInvoiceCosts
{
    public function __construct(private readonly UpdateMovingAverage $updateMovingAverage) {}

    public function handle(Document $invoice): void
    {
        if ($invoice->document_type !== DocumentType::SupplierInvoice) {
            return;
        }

        $matches = PurchaseMatch::query()
            ->whereHas('supplierInvoiceLine', fn ($query) => $query
                ->where('document_id', $invoice->id))
            ->orderByDesc('id')
            ->lockForUpdate()
            ->get();

        foreach ($matches as $match) {
            if ($match->product_id === null || $match->cost_value_delta === null) {
                continue;
            }

            $laterActive = PurchaseMatch::query()
                ->where('product_id', $match->product_id)
                ->where('id', '>', $match->id)
                ->whereHas('supplierInvoiceLine', fn ($query) => $query
                    ->where('document_id', '!=', $invoice->id))
                ->whereHas('supplierInvoiceLine.document', fn ($query) => $query
                    ->where('document_type', DocumentType::SupplierInvoice->value)
                    ->where('status', 'posted'))
                ->whereDoesntHave('supplierInvoiceLine.document.incomingRelations', fn ($query) => $query
                    ->where('relation_type', 'reversal_of'))
                ->whereDoesntHave('supplierInvoiceLine.document.outgoingRelations', fn ($query) => $query
                    ->where('relation_type', 'reversal_of'))
                ->exists();

            if ($laterActive) {
                throw new DomainException(
                    'Alış faturası maliyeti terslenmeden önce aynı ürünün daha sonraki alış faturaları terslenmelidir.',
                );
            }

            $this->updateMovingAverage->applyValueDelta(
                (int) $match->product_id,
                bcmul((string) $match->cost_value_delta, '-1', 4),
                (string) ($match->previous_last_purchase_price ?? '0.0000'),
                $match->previous_last_purchase_at?->toDateTimeString(),
            );
        }
    }
}

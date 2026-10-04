<?php

namespace App\Queries\Purchases;

use App\Enums\DocumentType;
use App\Models\Period\Document;
use App\Models\Period\PurchaseMatch;

final class BuildSupplierPerformance
{
    /** @return array<string,int|string> */
    public function handle(int $contactId): array
    {
        $orders = Document::query()
            ->where('document_type', DocumentType::PurchaseOrder->value)
            ->where('contact_id', $contactId)
            ->whereIn('status', ['approved', 'sent', 'closed'])
            ->count();

        $receipts = Document::query()
            ->where('document_type', DocumentType::GoodsReceipt->value)
            ->where('contact_id', $contactId)
            ->where('status', 'posted')
            ->whereDoesntHave('incomingRelations', fn ($query) => $query->where('relation_type', 'reversal_of'))
            ->whereDoesntHave('outgoingRelations', fn ($query) => $query->where('relation_type', 'reversal_of'))
            ->with('lines.sourceLine.document')
            ->get();

        $onTime = 0;
        $datedReceipts = 0;

        foreach ($receipts as $receipt) {
            $order = $receipt->lines
                ->map(fn ($line) => $line->sourceLine?->document)
                ->filter()
                ->first();

            if (! $order || $order->due_date === null) {
                continue;
            }

            $datedReceipts++;

            if ($receipt->document_date->lessThanOrEqualTo($order->due_date)) {
                $onTime++;
            }
        }

        $invoices = Document::query()
            ->where('document_type', DocumentType::SupplierInvoice->value)
            ->where('contact_id', $contactId)
            ->where('status', 'posted')
            ->whereDoesntHave('incomingRelations', fn ($query) => $query->where('relation_type', 'reversal_of'))
            ->whereDoesntHave('outgoingRelations', fn ($query) => $query->where('relation_type', 'reversal_of'))
            ->count();

        $variances = PurchaseMatch::query()
            ->whereHas('supplierInvoiceLine.document', fn ($query) => $query
                ->where('contact_id', $contactId)
                ->where('status', 'posted')
                ->whereDoesntHave('incomingRelations', fn ($nested) => $nested->where('relation_type', 'reversal_of'))
                ->whereDoesntHave('outgoingRelations', fn ($nested) => $nested->where('relation_type', 'reversal_of')))
            ->pluck('price_variance_rate');

        $absoluteVariance = '0.0000';

        foreach ($variances as $variance) {
            $value = (string) $variance;
            $absoluteVariance = bcadd(
                $absoluteVariance,
                str_starts_with($value, '-') ? substr($value, 1) : $value,
                4,
            );
        }

        $averageVariance = $variances->isEmpty()
            ? '0.0000'
            : bcdiv($absoluteVariance, (string) $variances->count(), 4);
        $onTimeRate = $datedReceipts === 0
            ? '0.0000'
            : bcdiv(bcmul((string) $onTime, '100', 4), (string) $datedReceipts, 4);

        return [
            'purchase_orders' => $orders,
            'goods_receipts' => $receipts->count(),
            'supplier_invoices' => $invoices,
            'on_time_receipt_rate' => $onTimeRate,
            'average_price_variance_rate' => $averageVariance,
        ];
    }
}

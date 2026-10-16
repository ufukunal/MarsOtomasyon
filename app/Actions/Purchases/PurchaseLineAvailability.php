<?php

namespace App\Actions\Purchases;

use App\Enums\DocumentType;
use App\Models\Period\DocumentLine;
use Illuminate\Support\Facades\DB;

final class PurchaseLineAvailability
{
    public function __construct(private readonly ResolvePurchaseLineage $lineage) {}

    public function orderReceiptRemaining(DocumentLine $orderLine): string
    {
        $received = '0.000';

        $children = DocumentLine::query()
            ->with('document')
            ->whereNotNull('source_line_id')
            ->whereHas('document', fn ($query) => $query
                ->where('status', 'posted')
                ->where('document_type', DocumentType::GoodsReceipt->value))
            ->get();

        foreach ($children as $child) {
            if ($this->isReversed((int) $child->document_id)) {
                continue;
            }

            $lineage = $this->lineage->handle($child);

            if ($lineage['purchase_order_line_id'] === (int) $orderLine->id) {
                $received = bcadd($received, (string) $child->quantity, 3);
            }
        }

        return bcsub((string) $orderLine->quantity, $received, 3);
    }

    /**
     * A supplier dispatch reserves an order quantity without changing stock.
     * Direct goods receipts reserve separate quantities. A receipt generated
     * from an existing dispatch must not be counted twice.
     */
    public function orderDispatchRemaining(DocumentLine $orderLine): string
    {
        $reserved = '0.000';
        $dispatches = DocumentLine::query()
            ->where('source_line_id', $orderLine->id)
            ->whereHas('document', fn ($query) => $query
                ->where('document_type', DocumentType::PurchaseDispatch->value)
                ->whereIn('status', ['draft', 'confirmed']))
            ->get();

        foreach ($dispatches as $dispatch) {
            $reserved = bcadd($reserved, (string) $dispatch->quantity, 3);
        }

        $directReceipts = DocumentLine::query()
            ->where('source_line_id', $orderLine->id)
            ->whereHas('document', fn ($query) => $query
                ->where('document_type', DocumentType::GoodsReceipt->value)
                ->whereIn('status', ['draft', 'posted']))
            ->get();

        foreach ($directReceipts as $receipt) {
            if ($this->isReversed((int) $receipt->document_id)) {
                continue;
            }
            $fromDispatch = DB::connection('period')->table('document_relations')
                ->where('target_document_id', $receipt->document_id)
                ->where('relation_type', 'purchase_dispatch_to_goods_receipt')
                ->exists();
            if (! $fromDispatch) {
                $reserved = bcadd($reserved, (string) $receipt->quantity, 3);
            }
        }

        return bcsub((string) $orderLine->quantity, $reserved, 3);
    }

    public function receiptInvoiceRemaining(DocumentLine $receiptLine): string
    {
        $invoiced = '0.000';

        $invoiceLines = DocumentLine::query()
            ->with('document')
            ->whereNotNull('source_line_id')
            ->whereHas('document', fn ($query) => $query
                ->where('status', 'posted')
                ->where('document_type', DocumentType::SupplierInvoice->value))
            ->get();

        foreach ($invoiceLines as $line) {
            if ($this->isReversed((int) $line->document_id)) {
                continue;
            }

            $lineage = $this->lineage->handle($line);

            if ($lineage['goods_receipt_line_id'] === (int) $receiptLine->id) {
                $invoiced = bcadd($invoiced, (string) $line->quantity, 3);
            }
        }

        return bcsub((string) $receiptLine->quantity, $invoiced, 3);
    }

    private function isReversed(int $documentId): bool
    {
        return DB::connection('period')->table('document_relations')
            ->where('relation_type', 'reversal_of')
            ->where('target_document_id', $documentId)
            ->exists();
    }
}

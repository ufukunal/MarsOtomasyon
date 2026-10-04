<?php

namespace App\Actions\Purchases;

use App\Enums\DocumentType;
use App\Models\Period\DocumentLine;
use DomainException;

final class ResolvePurchaseLineage
{
    /** @return array{purchase_order_line_id:?int,goods_receipt_line_id:?int,line_ids:list<int>} */
    public function handle(DocumentLine $line): array
    {
        $visited = [];
        $lineIds = [];
        $purchaseOrderLineId = null;
        $goodsReceiptLineId = null;
        $current = $line;

        while ($current->source_line_id !== null) {
            if (isset($visited[$current->id])) {
                throw new DomainException('Alış belge satırı kaynak zincirinde cycle bulundu.');
            }

            $visited[$current->id] = true;
            $parent = DocumentLine::query()->with('document')->findOrFail($current->source_line_id);
            $lineIds[] = (int) $parent->id;

            if ($parent->document->document_type === DocumentType::GoodsReceipt
                && $goodsReceiptLineId === null) {
                $goodsReceiptLineId = (int) $parent->id;
            }

            if ($parent->document->document_type === DocumentType::PurchaseOrder) {
                $purchaseOrderLineId = (int) $parent->id;
                break;
            }

            $current = $parent;
        }

        return [
            'purchase_order_line_id' => $purchaseOrderLineId,
            'goods_receipt_line_id' => $goodsReceiptLineId,
            'line_ids' => $lineIds,
        ];
    }
}

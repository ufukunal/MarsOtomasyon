<?php

namespace App\Livewire\Purchases;

use App\Enums\DocumentType;

class GoodsReceiptList extends BasePurchaseDocumentList
{
    protected function documentType(): DocumentType { return DocumentType::GoodsReceipt; }
    protected function permission(): string { return 'goods_receipts.view'; }
    protected function pageTitle(): string { return 'Mal Kabul'; }
    protected function editRoute(): string { return 'purchases.receipts.edit'; }
}

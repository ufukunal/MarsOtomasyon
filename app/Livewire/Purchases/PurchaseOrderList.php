<?php

namespace App\Livewire\Purchases;

use App\Enums\DocumentType;

class PurchaseOrderList extends BasePurchaseDocumentList
{
    protected function documentType(): DocumentType
    {
        return DocumentType::PurchaseOrder;
    }

    protected function permission(): string
    {
        return 'purchase_orders.view';
    }

    protected function pageTitle(): string
    {
        return 'Satınalma Siparişleri';
    }

    protected function editRoute(): string
    {
        return 'purchases.orders.edit';
    }

    protected function createRoute(): ?string
    {
        return 'purchases.orders.edit';
    }
}

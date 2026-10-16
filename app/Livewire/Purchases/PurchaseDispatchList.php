<?php

namespace App\Livewire\Purchases;

use App\Enums\DocumentType;

class PurchaseDispatchList extends BasePurchaseDocumentList
{
    protected function documentType(): DocumentType
    {
        return DocumentType::PurchaseDispatch;
    }

    protected function permission(): string
    {
        return 'purchase_dispatches.view';
    }

    protected function pageTitle(): string
    {
        return 'Alış İrsaliyeleri';
    }

    protected function editRoute(): string
    {
        return 'purchases.dispatches.edit';
    }
}

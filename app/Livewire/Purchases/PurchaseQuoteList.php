<?php

namespace App\Livewire\Purchases;

use App\Enums\DocumentType;

class PurchaseQuoteList extends BasePurchaseDocumentList
{
    protected function documentType(): DocumentType
    {
        return DocumentType::PurchaseQuote;
    }

    protected function permission(): string
    {
        return 'purchase_quotes.view';
    }

    protected function pageTitle(): string
    {
        return 'Alış Teklifleri';
    }

    protected function editRoute(): string
    {
        return 'purchases.quotes.edit';
    }

    protected function createRoute(): ?string
    {
        return 'purchases.quotes.edit';
    }
}

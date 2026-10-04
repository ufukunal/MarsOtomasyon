<?php

namespace App\Livewire\Sales;

use App\Enums\DocumentType;

class QuoteList extends BaseSalesDocumentList
{
    protected function documentType(): DocumentType
    {
        return DocumentType::Quote;
    }

    protected function permission(): string
    {
        return 'quotes.view';
    }

    protected function pageTitle(): string
    {
        return 'Teklifler';
    }

    protected function editRoute(): string
    {
        return 'sales.quotes.edit';
    }
}

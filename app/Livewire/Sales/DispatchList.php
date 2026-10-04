<?php

namespace App\Livewire\Sales;

use App\Enums\DocumentType;

class DispatchList extends BaseSalesDocumentList
{
    protected function documentType(): DocumentType { return DocumentType::Dispatch; }
    protected function permission(): string { return 'dispatches.view'; }
    protected function pageTitle(): string { return 'Satış İrsaliyeleri'; }
    protected function editRoute(): string { return 'sales.dispatches.edit'; }
}

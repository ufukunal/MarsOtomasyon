<?php

namespace App\Livewire\Sales;

use App\Enums\DocumentType;

class ProformaList extends BaseSalesDocumentList
{
    protected function documentType(): DocumentType
    {
        return DocumentType::Proforma;
    }

    protected function permission(): string
    {
        return 'proformas.view';
    }

    protected function pageTitle(): string
    {
        return 'Proformalar';
    }

    protected function editRoute(): string
    {
        return 'sales.proformas.show';
    }

    protected function createRoute(): ?string
    {
        return null;
    }
}

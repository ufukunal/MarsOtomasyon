<?php

namespace App\Livewire\Purchases;

use App\Enums\DocumentType;
use App\Livewire\Concerns\WithV65DocumentList;
use Livewire\Component;

abstract class BasePurchaseDocumentList extends Component
{
    use WithV65DocumentList;

    abstract protected function documentType(): DocumentType;

    abstract protected function permission(): string;

    abstract protected function pageTitle(): string;

    abstract protected function editRoute(): string;

    protected function createRoute(): ?string
    {
        return null;
    }
}

<?php

namespace App\Livewire\Purchases;

use App\Enums\DocumentType;
use App\Models\Period\Document;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

abstract class BasePurchaseDocumentList extends Component
{
    use WithPagination;

    abstract protected function documentType(): DocumentType;
    abstract protected function permission(): string;
    abstract protected function pageTitle(): string;
    abstract protected function editRoute(): string;

    protected function createRoute(): ?string
    {
        return null;
    }

    public function mount(): void
    {
        abort_unless(auth()->user()?->can($this->permission()), 403);
    }

    public function open(int|string $id): mixed
    {
        return $this->redirectRoute($this->editRoute(), ['id' => $id], navigate: false);
    }

    public function render(): View
    {
        return view('livewire.sales.document-list', [
            'documents' => Document::query()
                ->with('contact')
                ->where('document_type', $this->documentType()->value)
                ->orderByDesc('document_date')
                ->orderByDesc('id')
                ->paginate(50),
            'title' => $this->pageTitle(),
            'createRoute' => $this->createRoute(),
        ])->layout('layouts.app', ['pageTitle' => $this->pageTitle()]);
    }
}

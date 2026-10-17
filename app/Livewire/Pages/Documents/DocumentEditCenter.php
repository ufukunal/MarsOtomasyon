<?php

namespace App\Livewire\Pages\Documents;

use App\Models\Period\Document;
use App\Models\User;
use App\Support\Documents\DocumentEditCatalog;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Single index of the real edit/correction workflows for all DocumentType
 * records. Does not perform finance or stock mutations.
 */
class DocumentEditCenter extends Component
{
    use WithPagination;

    #[Url(as: 'ara')]
    public string $search = '';

    #[Url(as: 'tur')]
    public string $type = '';

    #[Url(as: 'durum')]
    public string $status = '';

    public function mount(): void
    {
        $user = auth()->user();
        abort_unless($user instanceof User && DocumentEditCatalog::permitted($user) !== [], 403);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedType(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $user = auth()->user();
        $allowed = DocumentEditCatalog::permitted($user instanceof User ? $user : null);
        abort_if($allowed === [], 403);

        $needle = mb_substr(trim($this->search), 0, 90);
        $type = isset($allowed[$this->type]) ? $this->type : '';
        $statuses = [
            'draft', 'pending_approval', 'internal_review', 'customer_review',
            'approved', 'confirmed', 'sent', 'posted', 'cancelled', 'reversed',
            'converted', 'closed',
        ];
        $status = in_array($this->status, $statuses, true) ? $this->status : '';

        $query = Document::query()
            ->with(['contact', 'incomingRelations', 'outgoingRelations'])
            ->whereIn('document_type', array_keys($allowed));

        // Removed/edited invoices retain their immutable accounting history,
        // but their reversal pairs do not appear as ordinary editable records.
        $query->where(function ($documents): void {
            $documents->whereNotIn('document_type', [
                \App\Enums\DocumentType::SalesInvoice->value,
                \App\Enums\DocumentType::SupplierInvoice->value,
            ])->orWhere(function ($invoices): void {
                $invoices->whereDoesntHave('incomingRelations', fn ($relation) => $relation
                    ->where('relation_type', 'reversal_of'))
                    ->whereDoesntHave('outgoingRelations', fn ($relation) => $relation
                        ->where('relation_type', 'reversal_of'));
            });
        });

        if ($type !== '') {
            $query->where('document_type', $type);
        }
        if ($status !== '') {
            $query->where('status', $status);
        }
        if ($needle !== '') {
            $pattern = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $needle).'%';
            $query->where(function ($q) use ($pattern): void {
                $q->where('number', 'ilike', $pattern);
                if (auth()->user()?->can('contacts.view')) {
                    $q->orWhereHas('contact', function ($c) use ($pattern): void {
                        $c->where('code', 'ilike', $pattern)
                            ->orWhere('title', 'ilike', $pattern);
                    });
                }
            });
        }

        return view('livewire.pages.documents.document-edit-center', [
            'documents' => $query->orderByDesc('document_date')->orderByDesc('id')->paginate(25),
            'availableTypes' => $allowed,
            'statusOptions' => $statuses,
            'otherModules' => array_values(array_filter(
                DocumentEditCatalog::relatedModules(),
                static fn (array $module): bool => $user instanceof User && $user->can($module['permission']),
            )),
        ])->layout('layouts.app', ['pageTitle' => 'Belge Düzenleme Merkezi']);
    }
}

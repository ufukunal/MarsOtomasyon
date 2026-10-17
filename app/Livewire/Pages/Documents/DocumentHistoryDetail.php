<?php

namespace App\Livewire\Pages\Documents;

use App\Models\Period\Document;
use App\Models\User;
use App\Support\Documents\DocumentEditCatalog;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Read-only, exact-record history for all 18 persisted document types.
 *
 * No mutation methods are exposed. The active user's permission is checked
 * both on the initial route and every Livewire render/hydration.
 */
class DocumentHistoryDetail extends Component
{
    #[Locked]
    public int $documentId;

    public function mount(int $id): void
    {
        abort_unless($id > 0, 404);
        $this->documentId = $id;
        $this->authorizedDocument();
    }

    private function authorizedDocument(): Document
    {
        $document = Document::query()->with([
            'contact', 'lines', 'incomingRelations.sourceDocument',
            'outgoingRelations.targetDocument',
        ])->findOrFail($this->documentId);

        $user = auth()->user();
        $entry = DocumentEditCatalog::entries()[$document->document_type->value] ?? null;

        abort_unless(
            $entry !== null && $user instanceof User && $user->can($entry['permission']),
            403,
        );

        return $document;
    }

    public function render(): View
    {
        $document = $this->authorizedDocument();
        $user = auth()->user();
        $entry = DocumentEditCatalog::entries()[$document->document_type->value];

        $destination = DocumentEditCatalog::url($document, $entry);
        $historyUrl = route('documents.history', ['id' => $document->id]);

        // Do not show a misleading self-referential "Edit" link when the
        // user's permission or the document state only allows history.
        $editUrl = $destination === $historyUrl ? null : $destination;

        $allowed = DocumentEditCatalog::permitted($user instanceof User ? $user : null);

        $links = collect($document->incomingRelations)
            ->map(fn ($relation) => [
                'relation' => $relation->relation_type,
                'other' => $relation->sourceDocument,
            ])->concat(collect($document->outgoingRelations)->map(fn ($relation) => [
                'relation' => $relation->relation_type,
                'other' => $relation->targetDocument,
            ]))
            ->filter(fn (array $row): bool => $row['other'] instanceof Document
                && isset($allowed[$row['other']->document_type->value]))
            ->values();

        return view('livewire.pages.documents.document-history-detail', [
            'document' => $document,
            'entry' => $entry,
            'editUrl' => $editUrl,
            'linkedDocuments' => $links,
            'showContact' => $user instanceof User && $user->can('contacts.view'),
        ])->layout('layouts.app', ['pageTitle' => 'Belge Geçmişi']);
    }
}

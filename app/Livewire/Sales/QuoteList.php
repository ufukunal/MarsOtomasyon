<?php

namespace App\Livewire\Sales;

use App\Enums\DocumentType;
use App\Models\Period\Document;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

class QuoteList extends BaseSalesDocumentList
{
    /**
     * One row per numbered quote: list and CSV/Excel exports show only
     * the latest revision. Independent, unnumbered drafts remain visible.
     *
     * @return Builder<Document>
     */
    protected function listQuery(): Builder
    {
        return parent::listQuery()->where(function (Builder $quotes): void {
            $quotes->whereNull('documents.number')
                ->orWhereNotExists(function (QueryBuilder $newer): void {
                    $newer->selectRaw('1')
                        ->from('documents as later')
                        ->where('later.document_type', DocumentType::Quote->value)
                        ->whereColumn('later.number', 'documents.number')
                        ->where(function (QueryBuilder $revision): void {
                            $revision->whereColumn('later.revision_no', '>', 'documents.revision_no')
                                ->orWhere(function (QueryBuilder $tie): void {
                                    $tie->whereColumn('later.revision_no', 'documents.revision_no')
                                        ->whereColumn('later.id', '>', 'documents.id');
                                });
                        });
                });
        });
    }

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

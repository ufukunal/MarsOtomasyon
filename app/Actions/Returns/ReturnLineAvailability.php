<?php

namespace App\Actions\Returns;

use App\Enums\DocumentType;
use App\Models\Period\DocumentLine;
use App\Models\Period\DocumentRelation;

final class ReturnLineAvailability
{
    public function remaining(DocumentLine $sourceLine, DocumentType $returnType): string
    {
        $returned = '0.000';

        $children = DocumentLine::query()
            ->with('document')
            ->where('source_line_id', $sourceLine->id)
            ->whereHas('document', fn ($query) => $query
                ->where('document_type', $returnType->value)
                ->where('status', 'posted'))
            ->get();

        foreach ($children as $child) {
            if ($this->isReversed((int) $child->document_id)) {
                continue;
            }

            $returned = bcadd($returned, (string) $child->quantity, 3);
        }

        return bcsub((string) $sourceLine->quantity, $returned, 3);
    }

    private function isReversed(int $documentId): bool
    {
        return DocumentRelation::query()
            ->where('relation_type', 'reversal_of')
            ->where('target_document_id', $documentId)
            ->exists();
    }
}

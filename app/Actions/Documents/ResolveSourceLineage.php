<?php

namespace App\Actions\Documents;

use App\Enums\DocumentType;
use App\Models\Period\DocumentLine;
use DomainException;

final class ResolveSourceLineage
{
    /**
     * @return array{origin_order_line_id:?int,has_dispatch:bool,line_ids:list<int>}
     */
    public function handle(DocumentLine $line): array
    {
        $visited = [];
        $lineIds = [];
        $hasDispatch = false;
        $originOrderLineId = null;
        $current = $line;

        while ($current->source_line_id !== null) {
            if (isset($visited[$current->id])) {
                throw new DomainException('Belge satırı kaynak zincirinde cycle bulundu.');
            }

            $visited[$current->id] = true;
            $parent = DocumentLine::query()->with('document')->findOrFail($current->source_line_id);
            $lineIds[] = (int) $parent->id;

            if ($parent->document->document_type === DocumentType::Dispatch) {
                $hasDispatch = true;
            }

            if ($parent->document->document_type === DocumentType::SalesOrder) {
                $originOrderLineId = (int) $parent->id;
                break;
            }

            $current = $parent;
        }

        return [
            'origin_order_line_id' => $originOrderLineId,
            'has_dispatch' => $hasDispatch,
            'line_ids' => $lineIds,
        ];
    }
}

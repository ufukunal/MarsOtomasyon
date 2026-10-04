<?php

namespace App\Actions\Documents;

use App\Enums\DocumentType;
use App\Models\Period\DocumentLine;
use Illuminate\Support\Facades\DB;

final class SourceLineAvailability
{
    public function __construct(private readonly ResolveSourceLineage $lineage) {}

    public function orderRemaining(DocumentLine $orderLine): string
    {
        $shipped = '0.000';
        $directInvoiced = '0.000';

        $children = DocumentLine::query()
            ->with('document')
            ->whereNotNull('source_line_id')
            ->whereHas('document', fn ($query) => $query
                ->where('status', 'posted')
                ->whereIn('document_type', [DocumentType::Dispatch->value, DocumentType::SalesInvoice->value]))
            ->get();

        foreach ($children as $child) {
            if ($this->isReversed((int) $child->document_id)) {
                continue;
            }

            $lineage = $this->lineage->handle($child);

            if ($lineage['origin_order_line_id'] !== (int) $orderLine->id) {
                continue;
            }

            if ($child->document->document_type === DocumentType::Dispatch) {
                $shipped = bcadd($shipped, (string) $child->quantity, 3);
            } elseif (! $lineage['has_dispatch']) {
                $directInvoiced = bcadd($directInvoiced, (string) $child->quantity, 3);
            }
        }

        return bcsub(
            bcsub(
                bcsub((string) $orderLine->quantity, (string) $orderLine->cancelled_quantity, 3),
                $shipped,
                3,
            ),
            $directInvoiced,
            3,
        );
    }

    public function dispatchRemaining(DocumentLine $dispatchLine): string
    {
        $invoiced = '0.000';

        $invoiceLines = DocumentLine::query()
            ->with('document')
            ->whereHas('document', fn ($query) => $query
                ->where('status', 'posted')
                ->where('document_type', DocumentType::SalesInvoice->value))
            ->whereNotNull('source_line_id')
            ->get();

        foreach ($invoiceLines as $line) {
            if ($this->isReversed((int) $line->document_id)) {
                continue;
            }

            $lineage = $this->lineage->handle($line);

            if (in_array((int) $dispatchLine->id, $lineage['line_ids'], true)) {
                $invoiced = bcadd($invoiced, (string) $line->quantity, 3);
            }
        }

        return bcsub((string) $dispatchLine->quantity, $invoiced, 3);
    }

    private function isReversed(int $documentId): bool
    {
        return DB::connection('period')->table('document_relations')
            ->where('relation_type', 'reversal_of')
            ->where('target_document_id', $documentId)
            ->exists();
    }
}

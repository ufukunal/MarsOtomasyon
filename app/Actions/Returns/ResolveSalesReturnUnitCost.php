<?php

namespace App\Actions\Returns;

use App\Actions\Documents\ResolveSourceLineage;
use App\Enums\DocumentType;
use App\Models\Period\DocumentLine;
use App\Models\Period\ProductCost;
use App\Models\Period\StockMovement;

final class ResolveSalesReturnUnitCost
{
    public function __construct(private readonly ResolveSourceLineage $lineage) {}

    public function handle(DocumentLine $sourceInvoiceLine): string
    {
        $lineage = $this->lineage->handle($sourceInvoiceLine);

        if ($lineage['dispatch_line_id'] !== null) {
            $dispatchLine = DocumentLine::query()->find($lineage['dispatch_line_id']);

            if ($dispatchLine) {
                $cost = StockMovement::query()
                    ->where('document_type', DocumentType::Dispatch->value)
                    ->where('document_id', $dispatchLine->document_id)
                    ->where('product_id', $sourceInvoiceLine->product_id)
                    ->where('direction', 'out')
                    ->value('unit_cost');

                if ($cost !== null) {
                    return bcadd((string) $cost, '0', 4);
                }
            }
        }

        $cost = StockMovement::query()
            ->where('document_type', DocumentType::SalesInvoice->value)
            ->where('document_id', $sourceInvoiceLine->document_id)
            ->where('product_id', $sourceInvoiceLine->product_id)
            ->where('direction', 'out')
            ->value('unit_cost');

        if ($cost !== null) {
            return bcadd((string) $cost, '0', 4);
        }

        return bcadd((string) (ProductCost::query()
            ->where('product_id', $sourceInvoiceLine->product_id)
            ->value('moving_average') ?? '0'), '0', 4);
    }
}

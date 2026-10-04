<?php

namespace App\Queries\Imports;

use App\Enums\DocumentType;
use App\Models\Period\DocumentLine;
use App\Models\Period\ImportFile;
use App\Models\Period\ImportPackage;

final class BuildImportFileProfitability
{
    /** @return array<string,mixed> */
    public function handle(ImportFile $file): array
    {
        $packages = ImportPackage::query()
            ->where('import_file_id', $file->id)
            ->whereNotNull('product_id')
            ->whereNotNull('landed_unit_cost_try')
            ->get();

        $rows = [];
        $totalSales = '0.0000';
        $totalCost = '0.0000';
        $totalProfit = '0.0000';

        foreach ($packages->groupBy('product_id') as $productId => $productPackages) {
            $importQty = '0.000';
            $importCost = '0.0000';

            foreach ($productPackages as $package) {
                $importQty = bcadd($importQty, (string) $package->quantity, 3);
                $importCost = bcadd(
                    $importCost,
                    bcmul((string) $package->quantity, (string) $package->landed_unit_cost_try, 8),
                    4,
                );
            }

            [$netSoldQty, $netSales] = $this->netSalesForProduct(
                (int) $productId,
                $file->received_at?->toDateString(),
            );

            $attributableQty = bccomp($netSoldQty, $importQty, 3) > 0 ? $importQty : $netSoldQty;
            $attributableQty = bccomp($attributableQty, '0', 3) < 0 ? '0.000' : $attributableQty;
            $averageSale = bccomp($netSoldQty, '0', 3) > 0
                ? bcdiv($netSales, $netSoldQty, 6)
                : '0.000000';
            $attributableSales = bcadd(bcmul($attributableQty, $averageSale, 8), '0', 4);
            $averageImportCost = bccomp($importQty, '0', 3) > 0
                ? bcdiv($importCost, $importQty, 4)
                : '0.0000';
            $realizedCost = bcadd(bcmul($attributableQty, $averageImportCost, 8), '0', 4);
            $profit = bcsub($attributableSales, $realizedCost, 4);
            $margin = bccomp($attributableSales, '0', 4) > 0
                ? bcdiv(bcmul($profit, '100', 8), $attributableSales, 4)
                : '0.0000';

            $rows[(int) $productId] = [
                'product_id' => (int) $productId,
                'import_quantity' => $importQty,
                'attributable_sold_quantity' => $attributableQty,
                'sales_try' => $attributableSales,
                'cost_try' => $realizedCost,
                'profit_try' => $profit,
                'margin_rate' => $margin,
            ];

            $totalSales = bcadd($totalSales, $attributableSales, 4);
            $totalCost = bcadd($totalCost, $realizedCost, 4);
            $totalProfit = bcadd($totalProfit, $profit, 4);
        }

        return [
            'rows' => array_values($rows),
            'sales_try' => $totalSales,
            'cost_try' => $totalCost,
            'profit_try' => $totalProfit,
            'margin_rate' => bccomp($totalSales, '0', 4) > 0
                ? bcdiv(bcmul($totalProfit, '100', 8), $totalSales, 4)
                : '0.0000',
            'note' => 'Lot takibi olmadığı için satış atfı ürün bazında, stoğa giriş tarihinden sonraki net satışlardan ithal miktarla sınırlı olarak hesaplanır.',
        ];
    }

    /** @return array{0:string,1:string} */
    private function netSalesForProduct(int $productId, ?string $fromDate): array
    {
        if ($fromDate === null) {
            return ['0.000', '0.0000'];
        }

        $invoiceLines = DocumentLine::query()
            ->with('document')
            ->where('product_id', $productId)
            ->whereHas('document', fn ($query) => $query
                ->where('document_type', DocumentType::SalesInvoice->value)
                ->where('status', 'posted')
                ->whereDate('document_date', '>=', $fromDate)
                ->whereDoesntHave('incomingRelations', fn ($nested) => $nested->where('relation_type', 'reversal_of'))
                ->whereDoesntHave('outgoingRelations', fn ($nested) => $nested->where('relation_type', 'reversal_of')))
            ->get();

        $returnLines = DocumentLine::query()
            ->with('document')
            ->where('product_id', $productId)
            ->whereHas('document', fn ($query) => $query
                ->where('document_type', DocumentType::SalesReturn->value)
                ->where('status', 'posted')
                ->whereDate('document_date', '>=', $fromDate)
                ->whereDoesntHave('incomingRelations', fn ($nested) => $nested->where('relation_type', 'reversal_of'))
                ->whereDoesntHave('outgoingRelations', fn ($nested) => $nested->where('relation_type', 'reversal_of')))
            ->get();

        $qty = '0.000';
        $sales = '0.0000';

        foreach ($invoiceLines as $line) {
            $factor = bccomp((string) $line->document->subtotal, '0', 4) > 0
                ? bcdiv((string) $line->document->tax_base, (string) $line->document->subtotal, 10)
                : '1.0000000000';
            $qty = bcadd($qty, (string) $line->quantity, 3);
            $sales = bcadd($sales, bcmul((string) $line->line_total, $factor, 10), 4);
        }

        foreach ($returnLines as $line) {
            $factor = bccomp((string) $line->document->subtotal, '0', 4) > 0
                ? bcdiv((string) $line->document->tax_base, (string) $line->document->subtotal, 10)
                : '1.0000000000';
            $qty = bcsub($qty, (string) $line->quantity, 3);
            $sales = bcsub($sales, bcmul((string) $line->line_total, $factor, 10), 4);
        }

        return [$qty, $sales];
    }
}

<?php

namespace App\Queries\Imports;

use App\Models\Period\ImportContainer;
use App\Models\Period\ImportFile;
use App\Models\Period\ImportPackage;

final class BuildContainerProfitability
{
    public function __construct(private readonly BuildImportFileProfitability $fileProfitability) {}

    /** @return list<array<string,mixed>> */
    public function handle(ImportFile $file): array
    {
        $profitability = $this->fileProfitability->handle($file);
        $productRows = collect($profitability['rows'])->keyBy('product_id');
        $packages = ImportPackage::query()
            ->where('import_file_id', $file->id)
            ->whereNotNull('landed_unit_cost_try')
            ->get();
        $containers = ImportContainer::query()
            ->where('import_file_id', $file->id)
            ->orderBy('id')
            ->get();

        $productTotals = [];
        foreach ($packages->whereNotNull('product_id') as $package) {
            $productTotals[(int) $package->product_id] = bcadd(
                $productTotals[(int) $package->product_id] ?? '0.000',
                (string) $package->quantity,
                3,
            );
        }

        $totalVolume = '0.000000';
        foreach ($containers as $container) {
            $totalVolume = bcadd($totalVolume, (string) ($container->volume_cbm ?? '0'), 6);
        }

        $rows = [];

        foreach ($containers as $container) {
            $containerPackages = $packages->where('container_id', $container->id);
            $cost = '0.0000';
            $sales = '0.0000';
            $hasMappedQuantity = false;

            foreach ($containerPackages as $package) {
                $cost = bcadd(
                    $cost,
                    bcmul((string) $package->quantity, (string) $package->landed_unit_cost_try, 8),
                    4,
                );

                if ($package->product_id === null || ! isset($productRows[(int) $package->product_id])) {
                    continue;
                }

                $totalProductQty = $productTotals[(int) $package->product_id] ?? '0.000';

                if (bccomp($totalProductQty, '0', 3) <= 0) {
                    continue;
                }

                $hasMappedQuantity = true;
                $share = bcdiv((string) $package->quantity, $totalProductQty, 10);
                $sales = bcadd(
                    $sales,
                    bcmul((string) $productRows[(int) $package->product_id]['sales_try'], $share, 10),
                    4,
                );
            }

            if (! $hasMappedQuantity && bccomp($totalVolume, '0', 6) > 0) {
                $volumeShare = bcdiv((string) ($container->volume_cbm ?? '0'), $totalVolume, 10);
                $sales = bcadd(
                    bcmul((string) $profitability['sales_try'], $volumeShare, 10),
                    '0',
                    4,
                );
            }

            $profit = bcsub($sales, $cost, 4);
            $rows[] = [
                'container_id' => (int) $container->id,
                'container_no' => $container->container_no,
                'sales_try' => $sales,
                'cost_try' => $cost,
                'profit_try' => $profit,
                'margin_rate' => bccomp($sales, '0', 4) > 0
                    ? bcdiv(bcmul($profit, '100', 8), $sales, 4)
                    : '0.0000',
                'allocation_basis' => $hasMappedQuantity ? 'matched_quantity' : 'volume_share',
            ];
        }

        return $rows;
    }
}

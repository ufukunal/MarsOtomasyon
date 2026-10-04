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
            ->orderBy('id')
            ->get();
        $containers = ImportContainer::query()
            ->where('import_file_id', $file->id)
            ->orderBy('id')
            ->get();

        $rows = [];

        foreach ($containers as $container) {
            $rows[(int) $container->id] = [
                'container_id' => (int) $container->id,
                'container_no' => $container->container_no,
                'sales_try' => '0.0000',
                'cost_try' => '0.0000',
            ];
        }

        $productPackages = $packages
            ->whereNotNull('product_id')
            ->groupBy('product_id');
        $hasMappedQuantity = false;

        foreach ($productRows as $productId => $productRow) {
            $mappedPackages = ($productPackages->get($productId) ?? collect())
                ->filter(fn ($package) => bccomp((string) $package->quantity, '0', 3) > 0)
                ->sortBy('id')
                ->values();

            if ($mappedPackages->isEmpty()) {
                continue;
            }

            $totalProductQty = '0.000';

            foreach ($mappedPackages as $package) {
                $totalProductQty = bcadd($totalProductQty, (string) $package->quantity, 3);
            }

            if (bccomp($totalProductQty, '0', 3) <= 0) {
                continue;
            }

            $hasMappedQuantity = true;
            $allocatedSales = '0.0000';
            $allocatedCost = '0.0000';
            $lastIndex = $mappedPackages->count() - 1;

            foreach ($mappedPackages as $index => $package) {
                if ($index === $lastIndex) {
                    $packageSales = bcsub((string) $productRow['sales_try'], $allocatedSales, 4);
                    $packageCost = bcsub((string) $productRow['cost_try'], $allocatedCost, 4);
                } else {
                    $share = bcdiv((string) $package->quantity, $totalProductQty, 10);
                    $packageSales = bcadd(
                        bcmul((string) $productRow['sales_try'], $share, 10),
                        '0',
                        4,
                    );
                    $packageCost = bcadd(
                        bcmul((string) $productRow['cost_try'], $share, 10),
                        '0',
                        4,
                    );
                }

                $allocatedSales = bcadd($allocatedSales, $packageSales, 4);
                $allocatedCost = bcadd($allocatedCost, $packageCost, 4);

                $containerId = (int) $package->container_id;

                if (! isset($rows[$containerId])) {
                    continue;
                }

                $rows[$containerId]['sales_try'] = bcadd(
                    (string) $rows[$containerId]['sales_try'],
                    $packageSales,
                    4,
                );
                $rows[$containerId]['cost_try'] = bcadd(
                    (string) $rows[$containerId]['cost_try'],
                    $packageCost,
                    4,
                );
            }
        }

        if (! $hasMappedQuantity) {
            $eligibleContainers = $containers
                ->filter(fn ($container) => bccomp((string) ($container->volume_cbm ?? '0'), '0', 6) > 0)
                ->values();

            $totalVolume = '0.000000';

            foreach ($eligibleContainers as $container) {
                $totalVolume = bcadd($totalVolume, (string) $container->volume_cbm, 6);
            }

            if (bccomp($totalVolume, '0', 6) > 0) {
                $allocatedSales = '0.0000';
                $allocatedCost = '0.0000';
                $lastIndex = $eligibleContainers->count() - 1;

                foreach ($eligibleContainers as $index => $container) {
                    if ($index === $lastIndex) {
                        $containerSales = bcsub((string) $profitability['sales_try'], $allocatedSales, 4);
                        $containerCost = bcsub((string) $profitability['cost_try'], $allocatedCost, 4);
                    } else {
                        $share = bcdiv((string) $container->volume_cbm, $totalVolume, 10);
                        $containerSales = bcadd(
                            bcmul((string) $profitability['sales_try'], $share, 10),
                            '0',
                            4,
                        );
                        $containerCost = bcadd(
                            bcmul((string) $profitability['cost_try'], $share, 10),
                            '0',
                            4,
                        );
                    }

                    $allocatedSales = bcadd($allocatedSales, $containerSales, 4);
                    $allocatedCost = bcadd($allocatedCost, $containerCost, 4);
                    $containerId = (int) $container->id;
                    $rows[$containerId]['sales_try'] = $containerSales;
                    $rows[$containerId]['cost_try'] = $containerCost;
                }
            }
        }

        foreach ($rows as &$row) {
            $profit = bcsub((string) $row['sales_try'], (string) $row['cost_try'], 4);
            $row['profit_try'] = $profit;
            $row['margin_rate'] = bccomp((string) $row['sales_try'], '0', 4) > 0
                ? bcdiv(bcmul($profit, '100', 8), (string) $row['sales_try'], 4)
                : '0.0000';
            $row['allocation_basis'] = $hasMappedQuantity ? 'matched_quantity' : 'volume_share';
        }
        unset($row);

        return array_values($rows);
    }
}

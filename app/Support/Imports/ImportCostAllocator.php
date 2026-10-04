<?php

namespace App\Support\Imports;

use App\Models\Period\ImportCostAllocation;
use App\Models\Period\ImportCostItem;
use App\Models\Period\ImportFile;
use App\Models\Period\ImportPackage;
use DomainException;

final class ImportCostAllocator
{
    public function recalculate(ImportFile $file): void
    {
        $rate = bcadd((string) $file->exchange_rate, '0', 6);

        if (bccomp($rate, '0', 6) <= 0) {
            throw new DomainException('İthalat maliyeti için pozitif kur zorunludur.');
        }

        $packages = ImportPackage::query()
            ->where('import_file_id', $file->id)
            ->whereNotNull('product_id')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        if ($packages->isEmpty()) {
            throw new DomainException('Maliyet dağıtımı için eşleşmiş en az bir koli/satır zorunludur.');
        }

        foreach ($packages as $package) {
            if (bccomp((string) $package->quantity, '0', 3) <= 0) {
                throw new DomainException('İthalat paket miktarı pozitif olmalıdır.');
            }

            $goodsValueTry = bcadd(
                bcmul(
                    bcmul((string) $package->quantity, (string) $package->unit_price, 8),
                    $rate,
                    8,
                ),
                '0',
                4,
            );

            $package->setAttribute('goods_value_try', $goodsValueTry);
            $package->setAttribute('allocated_cost_try', '0.0000');
            $package->setAttribute('landed_unit_cost_try', null);
            $package->save();
        }

        $items = ImportCostItem::query()
            ->where('import_file_id', $file->id)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        ImportCostAllocation::query()
            ->whereIn('import_cost_item_id', $items->pluck('id'))
            ->delete();

        foreach ($items as $item) {
            $itemRate = $this->itemRate($file, $item);
            $amountTry = bcadd(bcmul((string) $item->amount, $itemRate, 8), '0', 4);

            $basis = [];
            $basisTotal = '0.00000000';

            foreach ($packages as $package) {
                $value = match ($item->allocation_basis) {
                    'value' => (string) $package->goods_value_try,
                    'weight' => (string) ($package->weight_kg ?? '0'),
                    'volume' => (string) ($package->volume_cbm ?? '0'),
                    default => throw new DomainException('İthalat maliyet dağıtım esası geçersiz.'),
                };

                $value = bcadd($value, '0', 8);
                $basis[(int) $package->id] = $value;
                $basisTotal = bcadd($basisTotal, $value, 8);
            }

            if (bccomp($basisTotal, '0', 8) <= 0 && bccomp($amountTry, '0', 4) > 0) {
                throw new DomainException("{$item->name} maliyet kalemi için dağıtım tabanı sıfır.");
            }

            $allocated = '0.0000';
            $lastIndex = $packages->count() - 1;

            foreach ($packages->values() as $index => $package) {
                $basisValue = $basis[(int) $package->id];
                $ratio = bccomp($basisTotal, '0', 8) === 0
                    ? '0.0000000000'
                    : bcdiv($basisValue, $basisTotal, 10);

                $amount = $index === $lastIndex
                    ? bcsub($amountTry, $allocated, 4)
                    : bcadd(bcmul($amountTry, $ratio, 10), '0', 4);

                $allocated = bcadd($allocated, $amount, 4);

                ImportCostAllocation::query()->create([
                    'import_cost_item_id' => $item->id,
                    'package_id' => $package->id,
                    'product_id' => $package->product_id,
                    'container_id' => $package->container_id,
                    'basis_value' => $basisValue,
                    'allocation_ratio' => $ratio,
                    'allocated_amount_try' => $amount,
                ]);
            }

            if (bccomp($allocated, $amountTry, 4) !== 0) {
                throw new DomainException('İthalat maliyet kalemi dağıtım toplamı eşleşmiyor.');
            }

            $item->setAttribute('exchange_rate', $itemRate);
            $item->setAttribute('amount_try', $amountTry);
            $item->allocated_at = now();
            $item->save();
        }

        foreach ($packages as $package) {
            $allocated = bcadd((string) ImportCostAllocation::query()
                ->where('package_id', $package->id)
                ->sum('allocated_amount_try'), '0', 4);
            $total = bcadd((string) $package->goods_value_try, $allocated, 4);
            $unit = bcdiv($total, (string) $package->quantity, 4);

            $package->setAttribute('allocated_cost_try', $allocated);
            $package->setAttribute('landed_unit_cost_try', $unit);
            $package->save();
        }
    }

    private function itemRate(ImportFile $file, ImportCostItem $item): string
    {
        if ($item->currency === 'TRY') {
            return '1.000000';
        }

        if ($item->currency === $file->currency) {
            return bcadd((string) $file->exchange_rate, '0', 6);
        }

        $rate = bcadd((string) ($item->exchange_rate ?? '0'), '0', 6);

        if (bccomp($rate, '0', 6) <= 0) {
            throw new DomainException("{$item->name} için {$item->currency}/TRY kuru zorunludur.");
        }

        return $rate;
    }
}

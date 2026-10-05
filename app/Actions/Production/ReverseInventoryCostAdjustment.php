<?php

namespace App\Actions\Production;

use App\Models\Period\InventoryCostAdjustment;
use App\Models\Period\ProductCost;
use DomainException;
use Illuminate\Support\Facades\DB;

final class ReverseInventoryCostAdjustment
{
    public function handle(InventoryCostAdjustment $adjustment, string $adjustmentDate): InventoryCostAdjustment
    {
        return DB::connection('period')->transaction(function () use ($adjustment, $adjustmentDate): InventoryCostAdjustment {
            $locked = InventoryCostAdjustment::query()->lockForUpdate()->findOrFail($adjustment->id);

            if ($locked->reason === 'reversal') {
                throw new DomainException('Ters adjustment tekrar terslenemez.');
            }

            if (InventoryCostAdjustment::query()
                ->where('adjustment_of_id', $locked->id)
                ->exists()) {
                throw new DomainException('Maliyet adjustment kaydı daha önce terslenmiş.');
            }

            $cost = ProductCost::query()
                ->where('product_id', $locked->product_id)
                ->lockForUpdate()
                ->firstOrFail();

            $before = bcadd((string) $cost->moving_average, '0', 4);
            $unit = bcmul((string) $locked->unit_adjustment_base, '-1', 4);
            $after = bcadd($before, $unit, 4);

            if (bccomp($after, '0', 4) < 0) {
                throw new DomainException('Maliyet adjustment ters kaydı moving average değerini negatife düşüremez.');
            }

            $cost->moving_average = $after;
            $cost->save();

            $actor = auth()->user();

            return InventoryCostAdjustment::query()->create([
                'product_id' => $locked->product_id,
                'production_completion_id' => $locked->production_completion_id,
                'production_service_allocation_id' => $locked->production_service_allocation_id,
                'adjustment_of_id' => $locked->id,
                'adjustment_date' => $adjustmentDate,
                'quantity_basis' => $locked->quantity_basis,
                'amount_base' => bcmul((string) $locked->amount_base, '-1', 4),
                'unit_adjustment_base' => $unit,
                'moving_average_before' => $before,
                'moving_average_after' => $after,
                'reason' => 'reversal',
                'created_by' => $actor?->id,
                'created_by_name' => $actor?->name,
            ]);
        }, attempts: 3);
    }
}

<?php

namespace App\Actions\Production;

use App\Models\Period\InventoryCostAdjustment;
use App\Models\Period\ProductCost;
use App\Support\Period\PeriodContext;
use DomainException;
use Illuminate\Support\Facades\DB;

final class ApplyInventoryCostAdjustment
{
    public function handle(
        int $productId,
        int $completionId,
        int $serviceAllocationId,
        string $adjustmentDate,
        string $quantityBasis,
        string $amountBase,
    ): InventoryCostAdjustment {
        PeriodContext::ensureWritable();

        $quantityBasis = bcadd($quantityBasis, '0', 3);
        $amountBase = bcadd($amountBase, '0', 4);

        if (bccomp($quantityBasis, '0', 3) <= 0) {
            throw new DomainException('Maliyet adjustment quantity basis pozitif olmalıdır.');
        }

        return DB::connection('period')->transaction(function () use (
            $productId,
            $completionId,
            $serviceAllocationId,
            $adjustmentDate,
            $quantityBasis,
            $amountBase,
        ): InventoryCostAdjustment {
            DB::connection('period')->table('product_costs')->insertOrIgnore([
                'product_id' => $productId,
                'last_purchase_price' => '0.0000',
                'moving_average' => '0.0000',
                'import_cost' => '0.0000',
                'production_cost' => '0.0000',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $cost = ProductCost::query()
                ->where('product_id', $productId)
                ->lockForUpdate()
                ->firstOrFail();

            $before = bcadd((string) $cost->moving_average, '0', 4);
            $unit = bcdiv($amountBase, $quantityBasis, 4);
            $after = bcadd($before, $unit, 4);

            if (bccomp($after, '0', 4) < 0) {
                throw new DomainException('Fason maliyet düzeltmesi moving average değerini negatife düşüremez.');
            }

            $cost->moving_average = $after;
            $cost->save();

            $actor = auth()->user();

            return InventoryCostAdjustment::query()->create([
                'product_id' => $productId,
                'production_completion_id' => $completionId,
                'production_service_allocation_id' => $serviceAllocationId,
                'adjustment_date' => $adjustmentDate,
                'quantity_basis' => $quantityBasis,
                'amount_base' => $amountBase,
                'unit_adjustment_base' => $unit,
                'moving_average_before' => $before,
                'moving_average_after' => $after,
                'reason' => 'subcontract_late_cost',
                'created_by' => $actor?->id,
                'created_by_name' => $actor?->name,
            ]);
        }, attempts: 3);
    }
}

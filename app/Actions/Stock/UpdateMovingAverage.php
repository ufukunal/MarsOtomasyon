<?php

namespace App\Actions\Stock;

use App\Models\Period\ProductCost;
use App\Models\Period\StockBalance;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class UpdateMovingAverage
{
    public function handle(
        int $productId,
        string $incomingQty,
        string $incomingUnitCost,
        ?string $reason = null,
        ?string $movementDate = null,
    ): string {
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

        $currentQty = (string) StockBalance::query()
            ->where('product_id', $productId)
            ->sum('quantity');

        if (bccomp($currentQty, '0', 3) <= 0) {
            $newAverage = bcadd($incomingUnitCost, '0', 4);
        } else {
            $currentValue = bcmul(
                $currentQty,
                (string) $cost->moving_average,
                8,
            );
            $incomingValue = bcmul($incomingQty, $incomingUnitCost, 8);
            $newValue = bcadd($currentValue, $incomingValue, 8);
            $newQty = bcadd($currentQty, $incomingQty, 3);
            $newAverage = bcdiv($newValue, $newQty, 4);
        }

        $cost->setAttribute('moving_average', $newAverage);

        if ($reason === 'purchase') {
            $cost->setAttribute('last_purchase_price', bcadd($incomingUnitCost, '0', 4));
            $cost->setAttribute(
                'last_purchase_at',
                $movementDate ? CarbonImmutable::parse($movementDate)->startOfDay() : now(),
            );
        }

        $cost->save();

        return $newAverage;
    }
}

<?php

namespace App\Actions\Stock;

use App\Models\Period\ProductCost;
use App\Models\Period\StockBalance;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

final class UpdateMovingAverage
{
    public function handle(
        int $productId,
        string $incomingQty,
        string $incomingUnitCost,
        ?string $reason = null,
        ?string $movementDate = null,
        bool $quantityAlreadyInStock = false,
    ): string {
        $cost = $this->lockCost($productId);
        $currentQty = $this->currentQuantity($productId);
        $incomingQty = bcadd($incomingQty, '0', 3);
        $incomingUnitCost = bcadd($incomingUnitCost, '0', 4);

        if ($quantityAlreadyInStock) {
            $newAverage = $this->revalueAlreadyReceived(
                $currentQty,
                (string) $cost->moving_average,
                $incomingQty,
                $incomingUnitCost,
            );
        } elseif (bccomp($currentQty, '0', 3) <= 0) {
            $newAverage = $incomingUnitCost;
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
            $cost->setAttribute('last_purchase_price', $incomingUnitCost);
            $cost->setAttribute(
                'last_purchase_at',
                $movementDate ? CarbonImmutable::parse($movementDate)->startOfDay() : now(),
            );
        }

        $cost->save();

        return $newAverage;
    }

    public function applyValueDelta(
        int $productId,
        string $valueDelta,
        ?string $lastPurchasePrice = null,
        ?string $movementDate = null,
    ): string {
        $cost = $this->lockCost($productId);
        $currentQty = $this->currentQuantity($productId);

        if (bccomp($currentQty, '0', 3) > 0) {
            $currentValue = bcmul($currentQty, (string) $cost->moving_average, 8);
            $newValue = bcadd($currentValue, $valueDelta, 8);

            if (bccomp($newValue, '0', 8) < 0) {
                throw new DomainException('Maliyet değer düzeltmesi stok değerini negatife düşüremez.');
            }

            $cost->setAttribute(
                'moving_average',
                bcdiv($newValue, $currentQty, 4),
            );
        }

        if ($lastPurchasePrice !== null) {
            $cost->setAttribute('last_purchase_price', bcadd($lastPurchasePrice, '0', 4));
            $cost->setAttribute(
                'last_purchase_at',
                $movementDate ? CarbonImmutable::parse($movementDate)->startOfDay() : null,
            );
        }

        $cost->save();

        return (string) $cost->moving_average;
    }

    private function lockCost(int $productId): ProductCost
    {
        DB::connection('period')->table('product_costs')->insertOrIgnore([
            'product_id' => $productId,
            'last_purchase_price' => '0.0000',
            'moving_average' => '0.0000',
            'import_cost' => '0.0000',
            'production_cost' => '0.0000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ProductCost::query()
            ->where('product_id', $productId)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function currentQuantity(int $productId): string
    {
        return bcadd((string) StockBalance::query()
            ->where('product_id', $productId)
            ->sum('quantity'), '0', 3);
    }

    private function revalueAlreadyReceived(
        string $currentQty,
        string $currentAverage,
        string $incomingQty,
        string $incomingUnitCost,
    ): string {
        if (bccomp($currentQty, '0', 3) <= 0) {
            return $incomingUnitCost;
        }

        $revalueQty = bccomp($incomingQty, $currentQty, 3) > 0
            ? $currentQty
            : $incomingQty;
        $currentValue = bcmul($currentQty, $currentAverage, 8);
        $unitDelta = bcsub($incomingUnitCost, $currentAverage, 8);
        $valueDelta = bcmul($revalueQty, $unitDelta, 8);
        $newValue = bcadd($currentValue, $valueDelta, 8);

        if (bccomp($newValue, '0', 8) < 0) {
            throw new DomainException('Alış faturası maliyet düzeltmesi stok değerini negatife düşüremez.');
        }

        return bcdiv($newValue, $currentQty, 4);
    }
}

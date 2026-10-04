<?php

namespace App\Actions\Stock;

use App\Actions\Periods\EnsurePeriodOpen;
use App\DataObjects\StockMovementData;
use App\Enums\ProductKind;
use App\Exceptions\NegativeStockException;
use App\Models\Period\Location;
use App\Models\Period\Product;
use App\Models\Period\ProductCost;
use App\Models\Period\StockBalance;
use App\Models\Period\StockMovement;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

final class RecordStockMovement
{
    public function __construct(
        private readonly EnsurePeriodOpen $ensurePeriodOpen,
        private readonly UpdateMovingAverage $updateAverage,
    ) {}

    public function handle(StockMovementData $data): StockMovement
    {
        $movementDate = CarbonImmutable::parse($data->movementDate)->startOfDay();

        $this->assertData($data);
        $this->ensurePeriodOpen->handle($movementDate);

        return DB::connection('period')->transaction(function () use ($data, $movementDate): StockMovement {
            $product = Product::query()->findOrFail($data->productId);
            Location::query()->findOrFail($data->locationId);

            if ($product->kind === ProductKind::Set) {
                throw new DomainException('Set ürün için fiziksel stok hareketi yazılamaz.');
            }

            DB::connection('period')->table('stock_balances')->insertOrIgnore([
                'product_id' => $data->productId,
                'location_id' => $data->locationId,
                'quantity' => '0.000',
                'reserved' => '0.000',
                'consignment_reserved' => '0.000',
                'quarantine' => '0.000',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $balance = StockBalance::query()
                ->where('product_id', $data->productId)
                ->where('location_id', $data->locationId)
                ->lockForUpdate()
                ->firstOrFail();

            $signedQuantity = $data->direction === 'in'
                ? $data->quantity
                : bcmul($data->quantity, '-1', 3);

            $after = bcadd((string) $balance->quantity, $signedQuantity, 3);

            if ($data->direction === 'out'
                && bccomp($after, '0', 3) < 0
                && ! $product->allow_negative_stock) {
                throw new NegativeStockException(
                    "{$product->code} için stok yetersiz.",
                );
            }

            DB::connection('period')->table('product_costs')->insertOrIgnore([
                'product_id' => $product->id,
                'last_purchase_price' => '0.0000',
                'moving_average' => '0.0000',
                'import_cost' => '0.0000',
                'production_cost' => '0.0000',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($data->direction === 'in' && $data->updatesAverage) {
                if ($data->unitCost === null) {
                    throw new DomainException('Maliyeti etkileyen stok girişinde birim maliyet zorunludur.');
                }

                $unitCost = bcadd($data->unitCost, '0', 4);
                $newAverage = $this->updateAverage->handle(
                    $product->id,
                    $data->quantity,
                    $unitCost,
                    $data->reason,
                    $movementDate->toDateString(),
                );
            } else {
                $cost = ProductCost::query()
                    ->where('product_id', $product->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $unitCost = $data->unitCost !== null
                    ? bcadd($data->unitCost, '0', 4)
                    : (string) $cost->moving_average;
                $newAverage = (string) $cost->moving_average;
            }

            $balance->quantity = $after;
            $balance->save();

            $movement = StockMovement::query()->create([
                'product_id' => $data->productId,
                'location_id' => $data->locationId,
                'product_code' => $product->code,
                'movement_date' => $movementDate->toDateString(),
                'direction' => $data->direction,
                'reason' => $data->reason,
                'quantity' => bcadd($data->quantity, '0', 3),
                'unit_cost' => $unitCost,
                'total_cost' => bcmul($data->quantity, $unitCost, 4),
                'balance_after' => $after,
                'avg_cost_after' => $newAverage,
                'document_type' => $data->documentType,
                'document_id' => $data->documentId,
                'document_no' => $data->documentNo,
                'note' => $data->note,
                'created_by' => $data->actorUserId,
                'created_by_name' => $data->actorUserName,
            ]);

            if (bccomp((string) $movement->balance_after, (string) $balance->quantity, 3) !== 0) {
                throw new DomainException('Stok yazma sonrası bakiye doğrulaması başarısız.');
            }

            return $movement;
        }, attempts: 3);
    }

    private function assertData(StockMovementData $data): void
    {
        if (! in_array($data->direction, ['in', 'out'], true)) {
            throw new DomainException('Stok hareket yönü in veya out olmalıdır.');
        }

        if (bccomp($data->quantity, '0', 3) <= 0) {
            throw new DomainException('Stok hareket miktarı pozitif olmalıdır.');
        }

        if ($data->unitCost !== null && bccomp($data->unitCost, '0', 4) < 0) {
            throw new DomainException('Stok hareket maliyeti negatif olamaz.');
        }

        if ($data->updatesAverage && $data->direction !== 'in') {
            throw new DomainException('Hareketli ortalama yalnız stok girişinde güncellenebilir.');
        }
    }
}

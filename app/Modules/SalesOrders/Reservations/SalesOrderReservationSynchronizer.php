<?php

namespace App\Modules\SalesOrders\Reservations;

use App\Foundation\Identity\SourceEffectIdentity;
use App\Modules\Inventory\Reservations\StockReservationService;
use App\Modules\SalesOrders\Actions\ResolvedSalesOrderDraft;
use App\Modules\SalesOrders\Actions\ResolvedSalesOrderLine;
use App\Modules\SalesOrders\Models\SalesOrder;
use App\Modules\SalesOrders\Models\SalesOrderReservationGeneration;
use Illuminate\Support\Facades\DB;
use LogicException;

final readonly class SalesOrderReservationSynchronizer
{
    private const SOURCE_TYPE = 'sales_order';

    public function __construct(
        private StockReservationService $reservations,
    ) {}

    public function sync(SalesOrder $order, ResolvedSalesOrderDraft $draft): void
    {
        if (DB::connection()->transactionLevel() < 1) {
            throw new LogicException('Sipariş rezervasyon senkronizasyonu business transaction içinde çalışmalıdır.');
        }

        $companyId = (int) $order->company_id;
        $orderId = (int) $order->getKey();
        $active = SalesOrderReservationGeneration::query()
            ->where('company_id', $companyId)
            ->where('sales_order_id', $orderId)
            ->whereNull('released_at')
            ->lockForUpdate()
            ->get()
            ->keyBy('logical_line_key');

        /** @var list<SalesOrderReservationGeneration> $toRelease */
        $toRelease = [];
        /** @var list<ResolvedSalesOrderLine> $toReserve */
        $toReserve = [];

        foreach ($draft->lines as $line) {
            $key = $line->logicalLineKey;
            /** @var SalesOrderReservationGeneration|null $current */
            $current = $active->get($key);

            if ($line->warehouseId === null || $line->locationId === null) {
                if ($current !== null) {
                    $toRelease[] = $current;
                    $active->forget($key);
                }

                continue;
            }

            if ($current !== null && $this->matches($current, $line)) {
                $active->forget($key);

                continue;
            }

            if ($current !== null) {
                $toRelease[] = $current;
                $active->forget($key);
            }

            $toReserve[] = $line;
        }

        foreach ($active as $generation) {
            $toRelease[] = $generation;
        }

        foreach ($toRelease as $generation) {
            $this->release($order, $generation);
        }

        foreach ($toReserve as $line) {
            $this->reserve($order, $line);
        }
    }

    private function matches(SalesOrderReservationGeneration $generation, ResolvedSalesOrderLine $line): bool
    {
        return (int) $generation->product_id === $line->productId
            && (int) $generation->warehouse_id === $line->warehouseId
            && (int) $generation->location_id === $line->locationId
            && (string) $generation->quantity === $line->calculation->quantity;
    }

    private function reserve(SalesOrder $order, ResolvedSalesOrderLine $line): void
    {
        $companyId = (int) $order->company_id;
        $orderId = (int) $order->getKey();
        $generation = ((int) SalesOrderReservationGeneration::query()
            ->where('company_id', $companyId)
            ->where('sales_order_id', $orderId)
            ->where('logical_line_key', $line->logicalLineKey)
            ->max('generation')) + 1;
        $sourceId = $this->sourceId($orderId, $line->logicalLineKey, $generation);

        $result = $this->reservations->reserve(
            new SourceEffectIdentity($companyId, self::SOURCE_TYPE, $sourceId, 'reservation.reserve'),
            $line->productId,
            (int) $line->warehouseId,
            (int) $line->locationId,
            $line->calculation->quantity,
        );

        SalesOrderReservationGeneration::query()->create([
            'company_id' => $companyId,
            'sales_order_id' => $orderId,
            'logical_line_key' => $line->logicalLineKey,
            'generation' => $generation,
            'product_id' => $line->productId,
            'warehouse_id' => $line->warehouseId,
            'location_id' => $line->locationId,
            'quantity' => $line->calculation->quantity,
            'stock_reservation_id' => $result->reservation->getKey(),
        ]);
    }

    private function release(SalesOrder $order, SalesOrderReservationGeneration $generation): void
    {
        $companyId = (int) $order->company_id;
        $sourceId = $this->sourceId(
            (int) $order->getKey(),
            (string) $generation->logical_line_key,
            (int) $generation->generation,
        );
        $result = $this->reservations->release(
            new SourceEffectIdentity($companyId, self::SOURCE_TYPE, $sourceId, 'reservation.release'),
            (int) $generation->stock_reservation_id,
        );

        $generation->released_at = $result->reservation->released_at;
        $generation->save();
    }

    private function sourceId(int $orderId, string $logicalLineKey, int $generation): string
    {
        return $orderId.':'.$logicalLineKey.':'.$generation;
    }
}

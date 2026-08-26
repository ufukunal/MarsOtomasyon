<?php

namespace App\Modules\Dispatches\Stock;

use App\Foundation\Identity\SourceEffectIdentity;
use App\Modules\Dispatches\Models\Dispatch;
use App\Modules\Dispatches\Models\DispatchLine;
use App\Modules\Inventory\Enums\StockMovementType;
use App\Modules\Inventory\Ledger\PostStockMovementData;
use App\Modules\Inventory\Ledger\StockMovementPoster;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Reservations\StockReservationService;
use App\Modules\SalesOrders\Models\SalesOrderLine;
use App\Modules\SalesOrders\Models\SalesOrderReservationGeneration;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

final readonly class DispatchStockOutService
{
    private const SOURCE_TYPE = 'dispatch_line';

    private const STOCK_EFFECT = 'stock.out';

    public function __construct(
        private StockMovementPoster $stock,
        private StockReservationService $reservations,
    ) {}

    /** @return list<StockMovement> */
    public function post(Dispatch $dispatch): array
    {
        $this->assertInsideTransaction();

        $companyId = (int) $dispatch->company_id;
        $lockedDispatch = Dispatch::query()
            ->where('company_id', $companyId)
            ->whereKey($dispatch->getKey())
            ->lockForUpdate()
            ->first();

        if (! $lockedDispatch instanceof Dispatch) {
            throw ValidationException::withMessages([
                'dispatch' => 'Stok çıkışı yapılacak irsaliye aktif şirkette bulunamadı.',
            ]);
        }

        /** @var list<StockMovement> $movements */
        $movements = [];
        foreach ($lockedDispatch->lines()->lockForUpdate()->get() as $line) {
            $movements[] = $this->postLine($line);
        }

        if ($movements === []) {
            throw ValidationException::withMessages([
                'dispatch' => 'Stok çıkışı için en az bir irsaliye satırı gereklidir.',
            ]);
        }

        return $movements;
    }

    private function postLine(DispatchLine $line): StockMovement
    {
        $companyId = (int) $line->company_id;
        $sourceId = (string) $line->getKey();
        $existing = StockMovement::query()
            ->where('company_id', $companyId)
            ->where('source_type', self::SOURCE_TYPE)
            ->where('source_id', $sourceId)
            ->where('effect_type', self::STOCK_EFFECT)
            ->first();

        if ($existing instanceof StockMovement) {
            $this->assertReplayMatches($existing, $line);

            return $existing;
        }

        $orderLine = SalesOrderLine::query()
            ->where('company_id', $companyId)
            ->where('sales_order_id', $line->sales_order_id)
            ->whereKey($line->sales_order_line_id)
            ->lockForUpdate()
            ->first();
        if (! $orderLine instanceof SalesOrderLine) {
            throw ValidationException::withMessages([
                'dispatch' => 'İrsaliye kaynak sipariş satırı bulunamadı.',
            ]);
        }

        $generation = $this->activeReservationGeneration($orderLine);
        if ($generation instanceof SalesOrderReservationGeneration) {
            $this->assertReservationScope($generation, $line);
            $comparison = $this->compare((string) $line->quantity, (string) $generation->quantity);
            if ($comparison > 0) {
                throw ValidationException::withMessages([
                    'quantity' => 'İrsaliye miktarı aktif sipariş rezervasyonunu aşamaz.',
                ]);
            }

            if ($comparison === 0) {
                $result = $this->reservations->consume(
                    $this->identity($line, 'reservation.consume'),
                    (int) $generation->stock_reservation_id,
                );
                $generation->released_at = $result->reservation->consumed_at;
                $generation->save();

                return $this->postMovement($line);
            }

            $released = $this->reservations->release(
                $this->identity($line, 'reservation.release'),
                (int) $generation->stock_reservation_id,
            );
            $generation->released_at = $released->reservation->released_at;
            $generation->save();

            $movement = $this->postMovement($line);
            $remaining = $this->subtract((string) $generation->quantity, (string) $line->quantity);
            $replacement = $this->reservations->reserve(
                $this->identity($line, 'reservation.remainder'),
                (int) $generation->product_id,
                (int) $generation->warehouse_id,
                (int) $generation->location_id,
                $remaining,
            );

            SalesOrderReservationGeneration::query()->create([
                'company_id' => $companyId,
                'sales_order_id' => (int) $generation->sales_order_id,
                'logical_line_key' => (string) $generation->logical_line_key,
                'generation' => $this->nextGeneration($generation),
                'product_id' => (int) $generation->product_id,
                'warehouse_id' => (int) $generation->warehouse_id,
                'location_id' => (int) $generation->location_id,
                'quantity' => $remaining,
                'stock_reservation_id' => (int) $replacement->reservation->getKey(),
            ]);

            return $movement;
        }

        return $this->postMovement($line);
    }

    private function activeReservationGeneration(SalesOrderLine $line): ?SalesOrderReservationGeneration
    {
        $logicalKey = $line->logical_line_key;
        if (! is_string($logicalKey) || $logicalKey === '') {
            return null;
        }

        return SalesOrderReservationGeneration::query()
            ->where('company_id', $line->company_id)
            ->where('sales_order_id', $line->sales_order_id)
            ->where('logical_line_key', $logicalKey)
            ->whereNull('released_at')
            ->lockForUpdate()
            ->first();
    }

    private function assertReservationScope(SalesOrderReservationGeneration $generation, DispatchLine $line): void
    {
        if ((int) $generation->product_id !== (int) $line->product_id
            || (int) $generation->warehouse_id !== (int) $line->warehouse_id
            || (int) $generation->location_id !== (int) $line->location_id) {
            throw ValidationException::withMessages([
                'dispatch' => 'Aktif sipariş rezervasyonu irsaliye stok allocation scope ile uyuşmuyor.',
            ]);
        }
    }

    private function postMovement(DispatchLine $line): StockMovement
    {
        $result = $this->stock->post(new PostStockMovementData(
            sourceEffect: $this->identity($line, self::STOCK_EFFECT),
            productId: (int) $line->product_id,
            warehouseId: (int) $line->warehouse_id,
            locationId: (int) $line->location_id,
            movementType: StockMovementType::DispatchOut,
            quantity: (string) $line->quantity,
            note: 'İrsaliye stok çıkışı #'.(int) $line->dispatch_id,
        ));

        return $result->movement;
    }

    private function identity(DispatchLine $line, string $effectType): SourceEffectIdentity
    {
        return new SourceEffectIdentity(
            (int) $line->company_id,
            self::SOURCE_TYPE,
            (string) $line->getKey(),
            $effectType,
        );
    }

    private function assertReplayMatches(StockMovement $movement, DispatchLine $line): void
    {
        if ($movement->movement_type !== StockMovementType::DispatchOut
            || (int) $movement->product_id !== (int) $line->product_id
            || (int) $movement->warehouse_id !== (int) $line->warehouse_id
            || (int) $movement->location_id !== (int) $line->location_id
            || $this->compare($this->absolute((string) $movement->quantity_delta), (string) $line->quantity) !== 0) {
            throw new LogicException('Persisted dispatch stock effect source dispatch line ile uyuşmuyor.');
        }
    }

    private function nextGeneration(SalesOrderReservationGeneration $generation): int
    {
        return ((int) SalesOrderReservationGeneration::query()
            ->where('company_id', $generation->company_id)
            ->where('sales_order_id', $generation->sales_order_id)
            ->where('logical_line_key', $generation->logical_line_key)
            ->max('generation')) + 1;
    }

    private function compare(string $left, string $right): int
    {
        $row = DB::selectOne('SELECT CASE WHEN CAST(? AS numeric) < CAST(? AS numeric) THEN -1 WHEN CAST(? AS numeric) > CAST(? AS numeric) THEN 1 ELSE 0 END AS value', [$left, $right, $left, $right]);

        return $row === null
            ? throw new LogicException('Numeric comparison did not return a value.')
            : (int) $row->value;
    }

    private function subtract(string $left, string $right): string
    {
        $row = DB::selectOne('SELECT CAST(CAST(? AS numeric) - CAST(? AS numeric) AS numeric(20,6))::text AS value', [$left, $right]);

        return $row === null
            ? throw new LogicException('Numeric subtraction did not return a value.')
            : (string) $row->value;
    }

    private function absolute(string $value): string
    {
        $row = DB::selectOne('SELECT abs(CAST(? AS numeric))::text AS value', [$value]);

        return $row === null
            ? throw new LogicException('Numeric absolute did not return a value.')
            : (string) $row->value;
    }

    private function assertInsideTransaction(): void
    {
        if (DB::connection()->transactionLevel() < 1) {
            throw new LogicException('İrsaliye stok çıkışı aynı business transaction içinde çalışmalıdır.');
        }
    }
}

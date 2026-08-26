<?php

namespace App\Modules\Dispatches\Actions;

use App\Foundation\Clock\Clock;
use App\Foundation\Identity\SourceEffectIdentity;
use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Dispatches\Enums\DispatchStatus;
use App\Modules\Dispatches\Models\Dispatch;
use App\Modules\Dispatches\Models\DispatchLine;
use App\Modules\Inventory\Enums\StockMovementType;
use App\Modules\Inventory\Ledger\StockMovementReverser;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Reservations\StockReservationService;
use App\Modules\SalesOrders\Enums\SalesOrderProgressType;
use App\Modules\SalesOrders\Models\SalesOrderLine;
use App\Modules\SalesOrders\Models\SalesOrderLineProgress;
use App\Modules\SalesOrders\Models\SalesOrderLineProgressEffect;
use App\Modules\SalesOrders\Models\SalesOrderReservationGeneration;
use App\Modules\SalesOrders\Progress\SalesOrderProgressService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

final readonly class CancelDispatch
{
    public function __construct(
        private ActiveCompanyContext $companyContext,
        private StockMovementReverser $stockReverser,
        private SalesOrderProgressService $progress,
        private StockReservationService $reservations,
        private Clock $clock,
    ) {}

    public function handle(int $dispatchId): Dispatch
    {
        $companyId = (int) $this->companyContext->requireCompany()->getKey();

        return DB::transaction(function () use ($companyId, $dispatchId): Dispatch {
            $dispatch = Dispatch::query()
                ->where('company_id', $companyId)
                ->whereKey($dispatchId)
                ->lockForUpdate()
                ->first();

            if (! $dispatch instanceof Dispatch) {
                throw ValidationException::withMessages([
                    'dispatch' => 'İrsaliye aktif şirkette bulunamadı.',
                ]);
            }

            if ($dispatch->statusEnum() === DispatchStatus::Cancelled) {
                return $dispatch;
            }

            if ($dispatch->statusEnum() !== DispatchStatus::Finalized) {
                throw ValidationException::withMessages([
                    'status' => 'Yalnız kesinleşmiş irsaliye iptal edilebilir.',
                ]);
            }

            $lines = $dispatch->lines()->lockForUpdate()->get();
            if ($lines->isEmpty()) {
                throw new LogicException('Kesinleşmiş irsaliye satırsız olamaz.');
            }

            /** @var DispatchLine $line */
            foreach ($lines as $line) {
                $this->reverseStock($line);
                $this->reverseProgress($line);
                $this->reconcileReservation($line);
            }

            $dispatch->forceFill([
                'status' => DispatchStatus::Cancelled,
                'cancelled_at' => $this->clock->now(),
            ])->save();

            return $dispatch->refresh();
        });
    }

    private function reverseStock(DispatchLine $line): void
    {
        $original = StockMovement::query()
            ->where('company_id', $line->company_id)
            ->where('source_type', 'dispatch_line')
            ->where('source_id', (string) $line->getKey())
            ->where('effect_type', 'stock.out')
            ->where('movement_type', StockMovementType::DispatchOut->value)
            ->first();

        if (! $original instanceof StockMovement) {
            throw new LogicException('İrsaliye stok çıkış effecti bulunamadı.');
        }

        $this->stockReverser->reverse(
            (int) $original->getKey(),
            $this->identity($line, 'stock.out.reverse'),
            'İrsaliye iptal stok iadesi #'.(int) $line->dispatch_id,
        );
    }

    private function reverseProgress(DispatchLine $line): void
    {
        $original = SalesOrderLineProgressEffect::query()
            ->where('company_id', $line->company_id)
            ->where('sales_order_id', $line->sales_order_id)
            ->where('sales_order_line_id', $line->sales_order_line_id)
            ->where('source_type', 'dispatch_line')
            ->where('source_id', (string) $line->getKey())
            ->where('effect_type', 'progress.dispatch')
            ->where('progress_type', SalesOrderProgressType::Dispatched->value)
            ->first();

        if (! $original instanceof SalesOrderLineProgressEffect) {
            throw new LogicException('İrsaliye sipariş sevk progress effecti bulunamadı.');
        }

        $this->progress->reverse(
            $this->identity($line, 'progress.dispatch.reverse'),
            (int) $original->getKey(),
        );
    }

    private function reconcileReservation(DispatchLine $line): void
    {
        $orderLine = SalesOrderLine::query()
            ->where('company_id', $line->company_id)
            ->where('sales_order_id', $line->sales_order_id)
            ->whereKey($line->sales_order_line_id)
            ->lockForUpdate()
            ->first();

        if (! $orderLine instanceof SalesOrderLine) {
            throw new LogicException('İrsaliye kaynak sipariş satırı bulunamadı.');
        }

        if ($orderLine->warehouse_id === null && $orderLine->location_id === null) {
            return;
        }

        $logicalKey = $orderLine->logical_line_key;
        if (! is_string($logicalKey) || $logicalKey === '' || $orderLine->warehouse_id === null || $orderLine->location_id === null) {
            throw new LogicException('Allocation bağlı sipariş satırının reservation kimliği eksik.');
        }

        $projection = SalesOrderLineProgress::query()
            ->where('company_id', $line->company_id)
            ->where('sales_order_id', $line->sales_order_id)
            ->where('sales_order_line_id', $line->sales_order_line_id)
            ->first();

        if (! $projection instanceof SalesOrderLineProgress) {
            throw new LogicException('Sipariş sevk kalan miktar projection satırı bulunamadı.');
        }

        $targetQuantity = $projection->getAttribute('dispatch_remaining_quantity');
        if (! is_string($targetQuantity)) {
            throw new LogicException('Sipariş sevk kalan miktar projection değeri geçersiz.');
        }

        $active = SalesOrderReservationGeneration::query()
            ->where('company_id', $line->company_id)
            ->where('sales_order_id', $line->sales_order_id)
            ->where('logical_line_key', $logicalKey)
            ->whereNull('released_at')
            ->lockForUpdate()
            ->first();

        if ($targetQuantity === '0.000000') {
            if ($active instanceof SalesOrderReservationGeneration) {
                $this->releaseGeneration($line, $active);
            }

            return;
        }

        if ($active instanceof SalesOrderReservationGeneration && $this->generationMatches($active, $orderLine, $targetQuantity)) {
            return;
        }

        if ($active instanceof SalesOrderReservationGeneration) {
            $this->releaseGeneration($line, $active);
        }

        $result = $this->reservations->reserve(
            $this->identity($line, 'reservation.reconcile.reserve'),
            (int) $orderLine->product_id,
            (int) $orderLine->warehouse_id,
            (int) $orderLine->location_id,
            $targetQuantity,
        );

        SalesOrderReservationGeneration::query()->create([
            'company_id' => (int) $orderLine->company_id,
            'sales_order_id' => (int) $orderLine->sales_order_id,
            'logical_line_key' => $logicalKey,
            'generation' => $this->nextGeneration($orderLine, $logicalKey),
            'product_id' => (int) $orderLine->product_id,
            'warehouse_id' => (int) $orderLine->warehouse_id,
            'location_id' => (int) $orderLine->location_id,
            'quantity' => $targetQuantity,
            'stock_reservation_id' => (int) $result->reservation->getKey(),
        ]);
    }

    private function releaseGeneration(DispatchLine $line, SalesOrderReservationGeneration $generation): void
    {
        $result = $this->reservations->release(
            $this->identity($line, 'reservation.reconcile.release'),
            (int) $generation->stock_reservation_id,
        );

        $generation->released_at = $result->reservation->released_at;
        $generation->save();
    }

    private function generationMatches(
        SalesOrderReservationGeneration $generation,
        SalesOrderLine $line,
        string $quantity,
    ): bool {
        return (int) $generation->product_id === (int) $line->product_id
            && (int) $generation->warehouse_id === (int) $line->warehouse_id
            && (int) $generation->location_id === (int) $line->location_id
            && (string) $generation->quantity === $quantity;
    }

    private function nextGeneration(SalesOrderLine $line, string $logicalKey): int
    {
        return ((int) SalesOrderReservationGeneration::query()
            ->where('company_id', $line->company_id)
            ->where('sales_order_id', $line->sales_order_id)
            ->where('logical_line_key', $logicalKey)
            ->max('generation')) + 1;
    }

    private function identity(DispatchLine $line, string $effectType): SourceEffectIdentity
    {
        return new SourceEffectIdentity(
            (int) $line->company_id,
            'dispatch_line',
            (string) $line->getKey(),
            $effectType,
        );
    }
}

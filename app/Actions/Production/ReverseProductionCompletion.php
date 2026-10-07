<?php

namespace App\Actions\Production;

use App\Actions\Periods\EnsurePeriodOpen;
use App\Actions\Stock\RecordStockMovement;
use App\DataObjects\StockMovementData;
use App\Models\Period\InventoryCostAdjustment;
use App\Models\Period\ProductCost;
use App\Models\Period\ProductionCompletion;
use App\Models\Period\ProductionConsumption;
use App\Models\Period\ProductionOrder;
use App\Models\Period\ProductionOutput;
use App\Models\Period\ProductionServiceAllocation;
use App\Support\Audit\AuditContext;
use App\Support\Auth\MutationAuthorizer;
use App\Support\Concurrency\IdempotencyKey;
use App\Support\Production\ProductionServiceCostAllocator;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

final class ReverseProductionCompletion
{
    public function __construct(
        private readonly EnsurePeriodOpen $ensurePeriodOpen,
        private readonly RecordStockMovement $recordStockMovement,
        private readonly ReverseInventoryCostAdjustment $reverseAdjustment,
        private readonly ProductionServiceCostAllocator $serviceCosts,
    ) {}

    public function handle(
        ProductionCompletion $completion,
        string $reversalDate,
        string $reason,
        string $idempotencyKey,
    ): ProductionCompletion {
        MutationAuthorizer::authorize('production_orders.cancel');
        $reason = trim($reason);

        if ($reason === '') {
            throw new DomainException('Production completion ters kayıt gerekçesi zorunludur.');
        }

        $id = IdempotencyKey::run(
            $idempotencyKey,
            'production-completion.reverse:'.$completion->id,
            fn (): int => DB::connection('period')->transaction(function () use (
                $completion,
                $reversalDate,
                $reason,
            ): int {
                $order = ProductionOrder::query()
                    ->lockForUpdate()
                    ->findOrFail((int) $completion->production_order_id);

                $this->serviceCosts->lockRelevantCompletions($order);

                $original = ProductionCompletion::query()
                    ->with(['order.product', 'consumptions', 'outputs', 'serviceAllocations'])
                    ->lockForUpdate()
                    ->findOrFail($completion->id);

                if ((int) $original->production_order_id !== (int) $order->id) {
                    throw new DomainException('Completion üretim emri bağlamı değişmiş.');
                }

                if ($original->reversal_of_id !== null
                    || ProductionCompletion::query()->where('reversal_of_id', $original->id)->exists()) {
                    throw new DomainException('Completion daha önce terslenmiş veya kendisi ters kayıt.');
                }

                $latestActiveId = ProductionCompletion::query()
                    ->where('production_order_id', $original->production_order_id)
                    ->whereNull('reversal_of_id')
                    ->whereDoesntHave('reversals')
                    ->orderByDesc('id')
                    ->value('id');

                if ((int) $latestActiveId !== (int) $original->id) {
                    throw new DomainException('Completion ters kayıtları üretim emrinde sondan başa yapılmalıdır.');
                }

                $date = CarbonImmutable::parse($reversalDate)->startOfDay();
                $this->ensurePeriodOpen->handle($date);
                $this->lockStockBalances($order, $original);

                $adjustments = InventoryCostAdjustment::query()
                    ->where('production_completion_id', $original->id)
                    ->where('reason', 'subcontract_late_cost')
                    ->whereDoesntHave('adjustmentOf')
                    ->orderByDesc('id')
                    ->lockForUpdate()
                    ->get();

                foreach ($adjustments as $adjustment) {
                    if (InventoryCostAdjustment::query()
                        ->where('adjustment_of_id', $adjustment->id)
                        ->exists()) {
                        continue;
                    }

                    $this->reverseAdjustment->handle($adjustment, $date->toDateString());
                }

                $cost = ProductCost::query()
                    ->where('product_id', $order->product_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (bccomp((string) $cost->moving_average, (string) $original->moving_average_after, 4) !== 0
                    || bccomp((string) $cost->production_cost, (string) $original->production_unit_cost, 4) !== 0) {
                    throw new DomainException(
                        'Bu completion sonrasında mamul maliyeti değişmiş. Önce daha sonraki maliyet/üretim etkileri terslenmelidir.',
                    );
                }

                $cost->moving_average = $original->moving_average_before;
                $cost->production_cost = $original->previous_production_cost;
                $cost->save();

                $actor = auth()->user();
                $reversal = ProductionCompletion::query()->create([
                    'production_order_id' => $original->production_order_id,
                    'completion_date' => $date->toDateString(),
                    'completed_quantity' => $original->completed_quantity,
                    'material_cost_total' => $original->material_cost_total,
                    'subcontract_service_cost_total' => $original->subcontract_service_cost_total,
                    'production_cost_total' => $original->production_cost_total,
                    'production_unit_cost' => $original->production_unit_cost,
                    'moving_average_before' => $original->moving_average_after,
                    'moving_average_after' => $original->moving_average_before,
                    'previous_production_cost' => $original->production_unit_cost,
                    'reversal_of_id' => $original->id,
                    'notes' => $reason,
                    'created_by' => $actor?->id,
                    'created_by_name' => $actor?->name,
                ]);

                foreach ($original->outputs->sortBy('id') as $output) {
                    $movement = $this->recordStockMovement->handle(new StockMovementData(
                        productId: (int) $order->product_id,
                        locationId: (int) $output->location_id,
                        movementDate: $date->toDateString(),
                        direction: 'out',
                        reason: 'reversal',
                        quantity: (string) $output->quantity,
                        unitCost: (string) $original->production_unit_cost,
                        updatesAverage: false,
                        documentType: 'production_completion',
                        documentId: (int) $reversal->id,
                        documentNo: $order->number,
                        note: $reason,
                        actorUserId: $actor?->id,
                        actorUserName: $actor?->name,
                    ));

                    ProductionOutput::query()->create([
                        'production_completion_id' => $reversal->id,
                        'location_id' => $output->location_id,
                        'quantity' => $output->quantity,
                        'stock_movement_id' => $movement->id,
                    ]);
                }

                foreach ($original->consumptions->sortBy('id') as $consumption) {
                    $quantity = bcadd(
                        (string) $consumption->consumed_quantity,
                        (string) $consumption->fire_quantity,
                        3,
                    );
                    $movement = $this->recordStockMovement->handle(new StockMovementData(
                        productId: (int) $consumption->component_product_id,
                        locationId: (int) $consumption->location_id,
                        movementDate: $date->toDateString(),
                        direction: 'in',
                        reason: 'reversal',
                        quantity: $quantity,
                        unitCost: (string) $consumption->unit_cost,
                        updatesAverage: false,
                        documentType: 'production_completion',
                        documentId: (int) $reversal->id,
                        documentNo: $order->number,
                        note: $reason,
                        actorUserId: $actor?->id,
                        actorUserName: $actor?->name,
                    ));

                    ProductionConsumption::query()->create([
                        'production_completion_id' => $reversal->id,
                        'component_product_id' => $consumption->component_product_id,
                        'location_id' => $consumption->location_id,
                        'consumed_quantity' => $consumption->consumed_quantity,
                        'fire_quantity' => $consumption->fire_quantity,
                        'unit_cost' => $consumption->unit_cost,
                        'total_cost' => $consumption->total_cost,
                        'stock_movement_id' => $movement->id,
                    ]);
                }

                ProductionServiceAllocation::query()
                    ->where('production_completion_id', $original->id)
                    ->update([
                        'allocated_amount_base' => '0.0000',
                        'applied_amount_base' => '0.0000',
                        'updated_at' => now(),
                    ]);

                $order->completed_quantity = bcsub(
                    (string) $order->completed_quantity,
                    (string) $original->completed_quantity,
                    3,
                );
                $remaining = $order->remainingQuantity();

                if (bccomp($remaining, '0', 3) === 0) {
                    $order->status = bccomp((string) $order->completed_quantity, '0', 3) > 0
                        ? 'completed'
                        : 'cancelled';
                } else {
                    $order->status = bccomp((string) $order->completed_quantity, '0', 3) > 0
                        ? 'in_progress'
                        : 'confirmed';
                }

                $order->version = (int) $order->version + 1;
                $order->save();

                $this->serviceCosts->recalculateOrderInvoices($order, $date->toDateString());

                AuditContext::period(
                    'Production completion ters kayıtla geri alındı.',
                    [
                        'production_order_id' => $order->id,
                        'original_completion_id' => $original->id,
                        'reversal_completion_id' => $reversal->id,
                        'reason' => $reason,
                    ],
                    $reversal,
                    'production_completion_reversed',
                );

                return (int) $reversal->id;
            }, attempts: 3),
        );

        return ProductionCompletion::query()
            ->with(['order.product', 'consumptions', 'outputs'])
            ->findOrFail((int) $id);
    }

    private function lockStockBalances(
        ProductionOrder $order,
        ProductionCompletion $completion,
    ): void {
        $keys = [];

        foreach ($completion->outputs as $output) {
            $productId = (int) $order->product_id;
            $locationId = (int) $output->location_id;
            $keys[$productId.':'.$locationId] = [$productId, $locationId];
        }

        foreach ($completion->consumptions as $consumption) {
            $productId = (int) $consumption->component_product_id;
            $locationId = (int) $consumption->location_id;
            $keys[$productId.':'.$locationId] = [$productId, $locationId];
        }

        $keys = array_values($keys);
        usort($keys, static function (array $left, array $right): int {
            $productOrder = $left[0] <=> $right[0];

            return $productOrder !== 0
                ? $productOrder
                : $left[1] <=> $right[1];
        });

        foreach ($keys as [$productId, $locationId]) {
            DB::connection('period')->table('stock_balances')->insertOrIgnore([
                'product_id' => $productId,
                'location_id' => $locationId,
                'quantity' => '0.000',
                'reserved' => '0.000',
                'consignment_reserved' => '0.000',
                'quarantine' => '0.000',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $balance = DB::connection('period')
                ->table('stock_balances')
                ->where('product_id', $productId)
                ->where('location_id', $locationId)
                ->lockForUpdate()
                ->first();

            if (! $balance) {
                throw new DomainException('Production reversal stock balance kilidi alınamadı.');
            }
        }
    }
}

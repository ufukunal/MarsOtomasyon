<?php

namespace App\Actions\Production;

use App\Actions\Periods\EnsurePeriodOpen;
use App\Actions\Stock\RecordStockMovement;
use App\Actions\Stock\UpdateMovingAverage;
use App\DataObjects\StockMovementData;
use App\Enums\LocationKind;
use App\Models\Period\Location;
use App\Models\Period\ProductCost;
use App\Models\Period\ProductionCompletion;
use App\Models\Period\ProductionConsumption;
use App\Models\Period\ProductionOrder;
use App\Models\Period\ProductionOutput;
use App\Support\Audit\AuditContext;
use App\Support\Auth\MutationAuthorizer;
use App\Support\Concurrency\IdempotencyKey;
use App\Support\Production\ProductionCostCalculator;
use App\Support\Production\ProductionServiceCostAllocator;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

final class PostProductionCompletion
{
    public function __construct(
        private readonly EnsurePeriodOpen $ensurePeriodOpen,
        private readonly RecordStockMovement $recordStockMovement,
        private readonly UpdateMovingAverage $updateMovingAverage,
        private readonly ProductionCostCalculator $costs,
        private readonly ProductionServiceCostAllocator $serviceCosts,
    ) {}

    /**
     * @param  array<int,array{consumed_quantity?:string,fire_quantity?:string,location_id?:int|null}>  $consumptions
     * @param  list<array{location_id:int,quantity:string}>  $outputs
     */
    public function handle(
        ProductionOrder $order,
        string $completionDate,
        string $completedQuantity,
        array $consumptions,
        array $outputs,
        string $idempotencyKey,
        ?string $notes = null,
    ): ProductionCompletion {
        MutationAuthorizer::authorize('production_orders.update');

        $id = IdempotencyKey::run(
            $idempotencyKey,
            'production-completion.post:'.$order->id,
            fn (): int => DB::connection('period')->transaction(function () use (
                $order,
                $completionDate,
                $completedQuantity,
                $consumptions,
                $outputs,
                $notes,
            ): int {
                $locked = ProductionOrder::query()
                    ->with('components.componentProduct')
                    ->lockForUpdate()
                    ->findOrFail($order->id);

                if (! in_array($locked->status, ['confirmed', 'in_progress'], true)) {
                    throw new DomainException('Completion yalnız açık/onaylı üretim emrine girilebilir.');
                }

                $date = CarbonImmutable::parse($completionDate)->startOfDay();
                $this->ensurePeriodOpen->handle($date);
                $completedQuantity = bcadd($completedQuantity, '0', 3);

                if (bccomp($completedQuantity, '0', 3) <= 0
                    || bccomp($completedQuantity, $locked->remainingQuantity(), 3) > 0) {
                    throw new DomainException('Completion miktarı pozitif olmalı ve üretim emri kalanını aşmamalıdır.');
                }

                $outputTotal = '0.000';
                $normalizedOutputs = [];

                foreach ($outputs as $output) {
                    $quantity = bcadd((string) $output['quantity'], '0', 3);

                    if (bccomp($quantity, '0', 3) <= 0) {
                        throw new DomainException('Production output miktarı pozitif olmalıdır.');
                    }

                    $location = Location::query()
                        ->where('is_active', true)
                        ->findOrFail((int) $output['location_id']);

                    if ($location->kind === LocationKind::Subcontractor) {
                        throw new DomainException('Mamul output fason lokasyona yazılamaz.');
                    }

                    $normalizedOutputs[] = [
                        'location_id' => (int) $location->id,
                        'quantity' => $quantity,
                    ];
                    $outputTotal = bcadd($outputTotal, $quantity, 3);
                }

                if ($normalizedOutputs === []
                    || bccomp($outputTotal, $completedQuantity, 3) !== 0) {
                    throw new DomainException('Output lokasyon miktar toplamı completion miktarına eşit olmalıdır.');
                }

                $normalizedConsumptions = [];

                foreach ($locked->components->sortBy('component_product_id') as $component) {
                    $input = $consumptions[(int) $component->component_product_id] ?? [];
                    $plannedForCompletion = bcadd(
                        bcdiv(
                            bcmul((string) $component->planned_base_quantity, $completedQuantity, 8),
                            (string) $locked->planned_quantity,
                            8,
                        ),
                        '0',
                        3,
                    );
                    $consumed = array_key_exists('consumed_quantity', $input)
                        ? bcadd((string) $input['consumed_quantity'], '0', 3)
                        : $plannedForCompletion;
                    $fire = bcadd((string) ($input['fire_quantity'] ?? '0'), '0', 3);

                    if (bccomp($consumed, '0', 3) < 0 || bccomp($fire, '0', 3) < 0) {
                        throw new DomainException('Actual consumption/fire negatif olamaz.');
                    }

                    $total = bcadd($consumed, $fire, 3);

                    if (bccomp($total, '0', 3) === 0) {
                        continue;
                    }

                    $locationId = isset($input['location_id']) && $input['location_id']
                        ? (int) $input['location_id']
                        : null;

                    if ($locked->production_type === 'subcontract') {
                        $locationId ??= (int) $locked->subcontractor_location_id;

                        if ($locationId !== (int) $locked->subcontractor_location_id) {
                            throw new DomainException('Fason completion component tüketimi fason lokasyondan yapılmalıdır.');
                        }
                    }

                    if ($locationId === null) {
                        throw new DomainException('Actual component consumption için kaynak lokasyon zorunludur.');
                    }

                    $location = Location::query()->where('is_active', true)->findOrFail($locationId);

                    if ($locked->production_type === 'internal' && $location->kind === LocationKind::Subcontractor) {
                        throw new DomainException('İç üretim fason lokasyon stokunu tüketemez.');
                    }

                    $normalizedConsumptions[] = [
                        'component_product_id' => (int) $component->component_product_id,
                        'location_id' => $locationId,
                        'planned_quantity' => $plannedForCompletion,
                        'consumed_quantity' => $consumed,
                        'fire_quantity' => $fire,
                        'consumption_deviation' => bcsub($consumed, $plannedForCompletion, 3),
                        'total_quantity' => $total,
                    ];
                }

                $this->serviceCosts->lockRelevantCompletions($locked);
                $this->lockStockBalances($locked, $normalizedConsumptions, $normalizedOutputs);

                $servicePlan = $this->serviceCosts->prepareNewCompletion(
                    $locked,
                    $completedQuantity,
                    $date->toDateString(),
                );

                $componentIds = $locked->components
                    ->pluck('component_product_id')
                    ->map(fn ($value): int => (int) $value)
                    ->push((int) $locked->product_id)
                    ->unique()
                    ->sort()
                    ->values()
                    ->all();

                foreach ($componentIds as $productId) {
                    DB::connection('period')->table('product_costs')->insertOrIgnore([
                        'product_id' => $productId,
                        'last_purchase_price' => '0.0000',
                        'moving_average' => '0.0000',
                        'import_cost' => '0.0000',
                        'production_cost' => '0.0000',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $costRows = ProductCost::query()
                    ->whereIn('product_id', $componentIds)
                    ->orderBy('product_id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('product_id');

                $materialRows = [];

                foreach ($normalizedConsumptions as $index => $row) {
                    $costRow = $costRows->get($row['component_product_id']);

                    if (! $costRow) {
                        throw new DomainException('Component product_costs snapshotı bulunamadı.');
                    }

                    $unitCost = bcadd((string) $costRow->moving_average, '0', 4);
                    $normalizedConsumptions[$index]['unit_cost'] = $unitCost;
                    $materialRows[] = [
                        'quantity' => $row['total_quantity'],
                        'unit_cost' => $unitCost,
                    ];
                }

                $materialCost = $this->costs->materialCost($materialRows);
                $serviceCost = (string) $servicePlan['service_cost'];
                $productionUnitCost = $this->costs->unitCost(
                    $materialCost,
                    $serviceCost,
                    $completedQuantity,
                );

                $finishedCost = $costRows->get((int) $locked->product_id);

                if (! $finishedCost) {
                    throw new DomainException('Mamul product_costs snapshotı bulunamadı.');
                }

                $movingBefore = bcadd((string) $finishedCost->moving_average, '0', 4);
                $previousProductionCost = bcadd((string) $finishedCost->production_cost, '0', 4);
                $movingAfter = $this->updateMovingAverage->handle(
                    (int) $locked->product_id,
                    $completedQuantity,
                    $productionUnitCost,
                    'production',
                    $date->toDateString(),
                );

                $actor = auth()->user();
                $completion = ProductionCompletion::query()->create([
                    'production_order_id' => $locked->id,
                    'completion_date' => $date->toDateString(),
                    'completed_quantity' => $completedQuantity,
                    'material_cost_total' => $materialCost,
                    'subcontract_service_cost_total' => $serviceCost,
                    'production_cost_total' => bcadd($materialCost, $serviceCost, 4),
                    'production_unit_cost' => $productionUnitCost,
                    'moving_average_before' => $movingBefore,
                    'moving_average_after' => $movingAfter,
                    'previous_production_cost' => $previousProductionCost,
                    'notes' => trim((string) $notes) ?: null,
                    'created_by' => $actor?->id,
                    'created_by_name' => $actor?->name,
                ]);

                foreach ($normalizedConsumptions as $row) {
                    $movement = $this->recordStockMovement->handle(new StockMovementData(
                        productId: $row['component_product_id'],
                        locationId: $row['location_id'],
                        movementDate: $date->toDateString(),
                        direction: 'out',
                        reason: 'production',
                        quantity: $row['total_quantity'],
                        unitCost: $row['unit_cost'],
                        updatesAverage: false,
                        documentType: 'production_completion',
                        documentId: (int) $completion->id,
                        documentNo: $locked->number,
                        actorUserId: $actor?->id,
                        actorUserName: $actor?->name,
                    ));

                    ProductionConsumption::query()->create([
                        'production_completion_id' => $completion->id,
                        'component_product_id' => $row['component_product_id'],
                        'location_id' => $row['location_id'],
                        'consumed_quantity' => $row['consumed_quantity'],
                        'fire_quantity' => $row['fire_quantity'],
                        'unit_cost' => $row['unit_cost'],
                        'total_cost' => bcadd(
                            bcmul($row['total_quantity'], $row['unit_cost'], 8),
                            '0',
                            4,
                        ),
                        'stock_movement_id' => $movement->id,
                    ]);
                }

                $finishedCost->refresh();
                $finishedCost->production_cost = $productionUnitCost;
                $finishedCost->save();

                foreach ($normalizedOutputs as $output) {
                    $movement = $this->recordStockMovement->handle(new StockMovementData(
                        productId: (int) $locked->product_id,
                        locationId: $output['location_id'],
                        movementDate: $date->toDateString(),
                        direction: 'in',
                        reason: 'production',
                        quantity: $output['quantity'],
                        unitCost: $productionUnitCost,
                        updatesAverage: false,
                        documentType: 'production_completion',
                        documentId: (int) $completion->id,
                        documentNo: $locked->number,
                        actorUserId: $actor?->id,
                        actorUserName: $actor?->name,
                    ));

                    ProductionOutput::query()->create([
                        'production_completion_id' => $completion->id,
                        'location_id' => $output['location_id'],
                        'quantity' => $output['quantity'],
                        'stock_movement_id' => $movement->id,
                    ]);
                }

                $this->serviceCosts->persistNewCompletion($completion, $servicePlan['shares']);

                if (bccomp(
                    (string) ProductCost::query()->where('product_id', $locked->product_id)->value('moving_average'),
                    $movingAfter,
                    4,
                ) !== 0) {
                    throw new DomainException('Production completion sonrası moving average snapshotı eşleşmiyor.');
                }

                $locked->completed_quantity = bcadd(
                    (string) $locked->completed_quantity,
                    $completedQuantity,
                    3,
                );
                $locked->status = bccomp($locked->remainingQuantity(), '0', 3) === 0
                    ? 'completed'
                    : 'in_progress';
                $locked->version = (int) $locked->version + 1;
                $locked->save();

                AuditContext::period(
                    'Production completion kesinleştirildi.',
                    [
                        'production_order_id' => $locked->id,
                        'production_completion_id' => $completion->id,
                        'completed_quantity' => $completedQuantity,
                        'material_cost' => $materialCost,
                        'service_cost' => $serviceCost,
                        'production_unit_cost' => $productionUnitCost,
                        'consumption_deviations' => array_values(array_filter(
                            array_map(
                                fn (array $row): array => [
                                    'component_product_id' => $row['component_product_id'],
                                    'planned_quantity' => $row['planned_quantity'],
                                    'consumed_quantity' => $row['consumed_quantity'],
                                    'fire_quantity' => $row['fire_quantity'],
                                    'deviation_quantity' => $row['consumption_deviation'],
                                ],
                                $normalizedConsumptions,
                            ),
                            fn (array $row): bool => bccomp($row['deviation_quantity'], '0', 3) !== 0,
                        )),
                    ],
                    $completion,
                    'production_completion_posted',
                );

                return (int) $completion->id;
            }, attempts: 3),
        );

        return ProductionCompletion::query()
            ->with(['order.product', 'consumptions.componentProduct', 'outputs.location', 'serviceAllocations'])
            ->findOrFail((int) $id);
    }

    /**
     * @param  list<array{component_product_id:int,location_id:int}>  $consumptions
     * @param  list<array{location_id:int,quantity:string}>  $outputs
     */
    private function lockStockBalances(
        ProductionOrder $order,
        array $consumptions,
        array $outputs,
    ): void {
        $keys = [];

        foreach ($consumptions as $row) {
            $productId = (int) $row['component_product_id'];
            $locationId = (int) $row['location_id'];
            $keys[$productId.':'.$locationId] = [$productId, $locationId];
        }

        foreach ($outputs as $row) {
            $productId = (int) $order->product_id;
            $locationId = (int) $row['location_id'];
            $keys[$productId.':'.$locationId] = [$productId, $locationId];
        }

        $keys = array_values($keys);
        usort($keys, static fn (array $left, array $right): int =>
            $left[0] <=> $right[0] ?: $left[1] <=> $right[1]
        );

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
                throw new DomainException('Production stock balance kilidi alınamadı.');
            }
        }
    }

}

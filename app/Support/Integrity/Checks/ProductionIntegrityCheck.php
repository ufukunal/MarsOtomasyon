<?php

namespace App\Support\Integrity\Checks;

use App\Models\Period\Document;
use App\Models\Period\DocumentLine;
use App\Models\Period\InventoryCostAdjustment;
use App\Models\Period\ProductCost;
use App\Models\Period\ProductionCompletion;
use App\Models\Period\ProductionOrder;
use App\Models\Period\ProductionServiceAllocation;
use App\Models\Period\ProductionServiceInvoice;
use App\Support\Integrity\IntegrityCheck;
use App\Support\Integrity\IntegrityResult;
use Illuminate\Support\Facades\Schema;

final class ProductionIntegrityCheck implements IntegrityCheck
{
    public function name(): string
    {
        return 'production_phase8';
    }

    public function run(): IntegrityResult
    {
        $started = hrtime(true);

        if (! Schema::connection('period')->hasTable('production_orders')) {
            return new IntegrityResult(
                checked: 0,
                mismatches: [],
                durationMs: $this->elapsed($started),
                meta: ['status' => 'not_applicable'],
            );
        }

        $orders = ProductionOrder::query()
            ->with([
                'components',
                'completions.reversals',
                'completions.consumptions.stockMovement',
                'completions.outputs.stockMovement',
            ])
            ->orderBy('id')
            ->get();
        $mismatches = [];

        foreach ($orders as $order) {
            $activeCompletions = $order->completions
                ->whereNull('reversal_of_id')
                ->filter(fn ($completion) => $completion->reversals->isEmpty());

            $completed = $activeCompletions->reduce(
                fn (string $sum, $completion): string => bcadd(
                    $sum,
                    (string) $completion->completed_quantity,
                    3,
                ),
                '0.000',
            );

            if (bccomp($completed, (string) $order->completed_quantity, 3) !== 0) {
                $mismatches[] = [
                    'production_order_id' => $order->id,
                    'reason' => 'completed_quantity_mismatch',
                ];
            }

            if ($order->status !== 'draft' && $order->status !== 'cancelled' && $order->components->isEmpty()) {
                $mismatches[] = [
                    'production_order_id' => $order->id,
                    'reason' => 'component_snapshot_missing',
                ];
            }

            foreach ($order->components as $component) {
                $expected = bcadd(
                    bcmul((string) $component->planned_quantity, (string) $component->conversion_factor, 8),
                    '0',
                    3,
                );

                if (bccomp((string) $component->planned_base_quantity, $expected, 3) !== 0) {
                    $mismatches[] = [
                        'production_order_component_id' => $component->id,
                        'reason' => 'component_conversion_mismatch',
                    ];
                }
            }

            foreach ($activeCompletions as $completion) {
                $outputQuantity = $completion->outputs->reduce(
                    fn (string $sum, $output): string => bcadd($sum, (string) $output->quantity, 3),
                    '0.000',
                );
                $materialCost = $completion->consumptions->reduce(
                    fn (string $sum, $consumption): string => bcadd($sum, (string) $consumption->total_cost, 4),
                    '0.0000',
                );
                $expectedTotal = bcadd(
                    $materialCost,
                    (string) $completion->subcontract_service_cost_total,
                    4,
                );
                $expectedUnit = bcdiv(
                    $expectedTotal,
                    (string) $completion->completed_quantity,
                    4,
                );

                if (bccomp($outputQuantity, (string) $completion->completed_quantity, 3) !== 0
                    || bccomp($materialCost, (string) $completion->material_cost_total, 4) !== 0
                    || bccomp($expectedTotal, (string) $completion->production_cost_total, 4) !== 0
                    || bccomp($expectedUnit, (string) $completion->production_unit_cost, 4) !== 0) {
                    $mismatches[] = [
                        'production_completion_id' => $completion->id,
                        'reason' => 'completion_cost_or_quantity_mismatch',
                    ];
                }

                foreach ($completion->consumptions as $consumption) {
                    $movement = $consumption->stockMovement;
                    $quantity = bcadd(
                        (string) $consumption->consumed_quantity,
                        (string) $consumption->fire_quantity,
                        3,
                    );

                    if ($movement === null
                        || $movement->direction !== 'out'
                        || $movement->reason !== 'production'
                        || $movement->document_type !== 'production_completion'
                        || (int) $movement->document_id !== (int) $completion->id
                        || bccomp((string) $movement->quantity, $quantity, 3) !== 0
                        || bccomp((string) $movement->unit_cost, (string) $consumption->unit_cost, 4) !== 0) {
                        $mismatches[] = [
                            'production_consumption_id' => $consumption->id,
                            'reason' => 'consumption_movement_mismatch',
                        ];
                    }
                }

                foreach ($completion->outputs as $output) {
                    $movement = $output->stockMovement;

                    if ($movement === null
                        || $movement->direction !== 'in'
                        || $movement->reason !== 'production'
                        || $movement->document_type !== 'production_completion'
                        || (int) $movement->document_id !== (int) $completion->id
                        || bccomp((string) $movement->quantity, (string) $output->quantity, 3) !== 0
                        || bccomp((string) $movement->unit_cost, (string) $completion->production_unit_cost, 4) !== 0) {
                        $mismatches[] = [
                            'production_output_id' => $output->id,
                            'reason' => 'output_movement_mismatch',
                        ];
                    }
                }
            }
        }

        $allocations = ProductionServiceAllocation::query()
            ->with(['invoiceLine.document', 'completion'])
            ->orderBy('id')
            ->get();

        $activeMappings = ProductionServiceInvoice::query()
            ->orderBy('purchase_invoice_id')
            ->orderBy('production_order_id')
            ->get()
            ->groupBy('purchase_invoice_id');

        foreach ($allocations as $allocation) {
            $mappingExists = $activeMappings
                ->get($allocation->purchase_invoice_id, collect())
                ->contains(fn ($mapping): bool => (int) $mapping->production_order_id === (int) $allocation->production_order_id);

            if ($allocation->invoiceLine === null
                || $allocation->invoiceLine->line_kind !== 'service'
                || $allocation->completion === null
                || (int) $allocation->completion->production_order_id !== (int) $allocation->production_order_id
                || bccomp((string) $allocation->allocated_amount_base, (string) $allocation->applied_amount_base, 4) !== 0
                || (! $mappingExists && bccomp((string) $allocation->allocated_amount_base, '0', 4) !== 0)) {
                $mismatches[] = [
                    'production_service_allocation_id' => $allocation->id,
                    'reason' => 'service_allocation_mismatch',
                ];
            }
        }

        foreach ($activeMappings as $invoiceId => $mappings) {
            $invoice = Document::query()->with('lines')->find((int) $invoiceId);

            if ($invoice === null) {
                $mismatches[] = [
                    'purchase_invoice_id' => (int) $invoiceId,
                    'reason' => 'service_invoice_mapping_document_missing',
                ];

                continue;
            }

            $orderIds = $mappings
                ->pluck('production_order_id')
                ->map(fn ($id): int => (int) $id)
                ->all();
            $hasEligibleCompletion = ProductionCompletion::query()
                ->whereIn('production_order_id', $orderIds)
                ->whereNull('reversal_of_id')
                ->whereDoesntHave('reversals')
                ->exists();

            if (! $hasEligibleCompletion) {
                continue;
            }

            foreach ($invoice->lines->where('line_kind', 'service') as $line) {
                $allocated = $allocations
                    ->where('purchase_invoice_line_id', $line->id)
                    ->whereIn('production_order_id', $orderIds)
                    ->reduce(
                        fn (string $sum, $allocation): string => bcadd(
                            $sum,
                            (string) $allocation->allocated_amount_base,
                            4,
                        ),
                        '0.0000',
                    );
                $expected = $this->serviceLineAmountBase($invoice, $line);

                if (bccomp($allocated, $expected, 4) !== 0) {
                    $mismatches[] = [
                        'purchase_invoice_id' => (int) $invoiceId,
                        'purchase_invoice_line_id' => $line->id,
                        'reason' => 'service_allocation_total_mismatch',
                        'allocated' => $allocated,
                        'expected' => $expected,
                    ];
                }
            }
        }

        $adjustments = InventoryCostAdjustment::query()->orderBy('id')->get();

        foreach ($adjustments as $adjustment) {
            $expectedAfter = bcadd(
                (string) $adjustment->moving_average_before,
                (string) $adjustment->unit_adjustment_base,
                4,
            );

            if (bccomp($expectedAfter, (string) $adjustment->moving_average_after, 4) !== 0
                || bccomp((string) $adjustment->quantity_basis, '0', 3) <= 0) {
                $mismatches[] = [
                    'inventory_cost_adjustment_id' => $adjustment->id,
                    'reason' => 'cost_adjustment_snapshot_mismatch',
                ];
            }
        }

        $latestByProduct = [];
        foreach (ProductionCompletion::query()
            ->with(['order', 'reversals'])
            ->whereNull('reversal_of_id')
            ->orderBy('id')
            ->get() as $completion) {
            if ($completion->reversals->isEmpty()) {
                $latestByProduct[(int) $completion->order->product_id] = $completion;
            }
        }

        foreach ($latestByProduct as $productId => $completion) {
            $snapshot = ProductCost::query()->where('product_id', $productId)->value('production_cost');

            if ($snapshot === null
                || bccomp((string) $snapshot, (string) $completion->production_unit_cost, 4) !== 0) {
                $mismatches[] = [
                    'product_id' => $productId,
                    'production_completion_id' => $completion->id,
                    'reason' => 'production_cost_snapshot_mismatch',
                ];
            }
        }

        return new IntegrityResult(
            checked: $orders->count() + $allocations->count() + $adjustments->count(),
            mismatches: $mismatches,
            durationMs: $this->elapsed($started),
        );
    }

    private function serviceLineAmountBase(Document $invoice, DocumentLine $target): string
    {
        $allocatedDiscount = '0.00000000';
        $lines = $invoice->lines->sortBy('id')->values();
        $last = $lines->count() - 1;

        foreach ($lines as $index => $line) {
            if (bccomp((string) $invoice->subtotal, '0', 4) === 0) {
                $discountShare = '0.00000000';
            } elseif ($index === $last) {
                $discountShare = bcsub((string) $invoice->discount_amount, $allocatedDiscount, 8);
            } else {
                $discountShare = bcdiv(
                    bcmul((string) $invoice->discount_amount, (string) $line->line_total, 8),
                    (string) $invoice->subtotal,
                    8,
                );
                $allocatedDiscount = bcadd($allocatedDiscount, $discountShare, 8);
            }

            if ((int) $line->id === (int) $target->id) {
                return bcadd(
                    bcmul(
                        bcsub((string) $line->line_total, $discountShare, 8),
                        (string) $invoice->exchange_rate,
                        8,
                    ),
                    '0',
                    4,
                );
            }
        }

        return '0.0000';
    }

    private function elapsed(int $started): int
    {
        return (int) round((hrtime(true) - $started) / 1_000_000);
    }
}

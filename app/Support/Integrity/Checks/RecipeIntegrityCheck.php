<?php

namespace App\Support\Integrity\Checks;

use App\Models\Period\ProductionOrder;
use App\Models\Period\ProductionRecipe;
use App\Support\Integrity\IntegrityCheck;
use App\Support\Integrity\IntegrityResult;
use Illuminate\Support\Facades\Schema;

final class RecipeIntegrityCheck implements IntegrityCheck
{
    public function name(): string
    {
        return 'recipes_phase8';
    }

    public function run(): IntegrityResult
    {
        $started = hrtime(true);

        if (! Schema::connection('period')->hasTable('production_recipes')) {
            return new IntegrityResult(
                checked: 0,
                mismatches: [],
                durationMs: $this->elapsed($started),
                meta: ['status' => 'not_applicable'],
            );
        }

        $recipes = ProductionRecipe::query()
            ->with(['product', 'lines.componentProduct'])
            ->orderBy('product_id')
            ->orderBy('revision_no')
            ->get();
        $mismatches = [];

        foreach ($recipes->groupBy('product_id') as $productId => $productRecipes) {
            if ($productRecipes->where('is_active', true)->count() !== 1) {
                $mismatches[] = [
                    'product_id' => (int) $productId,
                    'reason' => 'active_recipe_count_mismatch',
                ];
            }

            $previousRevision = 0;

            foreach ($productRecipes as $recipe) {
                if ((int) $recipe->revision_no <= $previousRevision
                    || bccomp((string) $recipe->output_quantity, '0', 3) <= 0
                    || $recipe->lines->isEmpty()) {
                    $mismatches[] = [
                        'recipe_id' => $recipe->id,
                        'reason' => 'recipe_header_invalid',
                    ];
                }

                $previousRevision = (int) $recipe->revision_no;
                $seen = [];

                foreach ($recipe->lines as $line) {
                    $componentId = (int) $line->component_product_id;
                    $expectedBase = bcadd(
                        bcmul((string) $line->quantity, (string) $line->conversion_factor, 8),
                        '0',
                        3,
                    );

                    if ($componentId === (int) $recipe->product_id
                        || isset($seen[$componentId])
                        || bccomp((string) $line->quantity, '0', 3) <= 0
                        || bccomp((string) $line->conversion_factor, '0', 6) <= 0
                        || bccomp((string) $line->base_quantity, $expectedBase, 3) !== 0
                        || $line->componentProduct?->kind?->value === 'set') {
                        $mismatches[] = [
                            'recipe_line_id' => $line->id,
                            'reason' => 'recipe_line_invalid',
                        ];
                    }

                    $seen[$componentId] = true;
                }
            }
        }

        $orders = ProductionOrder::query()
            ->with(['recipe.lines', 'components'])
            ->whereNotIn('status', ['draft'])
            ->orderBy('id')
            ->get();

        foreach ($orders as $order) {
            $recipe = $order->recipe;

            if ($recipe === null
                || (int) $recipe->product_id !== (int) $order->product_id
                || (int) $recipe->revision_no !== (int) $order->recipe_revision_no) {
                $mismatches[] = [
                    'production_order_id' => $order->id,
                    'reason' => 'order_recipe_revision_mismatch',
                ];

                continue;
            }

            if ($order->components->isEmpty()) {
                if ($order->status !== 'cancelled') {
                    $mismatches[] = [
                        'production_order_id' => $order->id,
                        'reason' => 'order_recipe_snapshot_missing',
                    ];
                }

                continue;
            }

            $components = $order->components->keyBy('component_product_id');

            if ($components->count() !== $recipe->lines->count()) {
                $mismatches[] = [
                    'production_order_id' => $order->id,
                    'reason' => 'order_recipe_snapshot_line_count_mismatch',
                ];
            }

            foreach ($recipe->lines as $line) {
                $component = $components->get($line->component_product_id);

                if ($component === null) {
                    $mismatches[] = [
                        'production_order_id' => $order->id,
                        'recipe_line_id' => $line->id,
                        'reason' => 'order_recipe_component_missing',
                    ];

                    continue;
                }

                $expectedQuantity = bcadd(
                    bcdiv(
                        bcmul((string) $line->quantity, (string) $order->planned_quantity, 8),
                        (string) $recipe->output_quantity,
                        8,
                    ),
                    '0',
                    3,
                );
                $expectedBase = bcadd(
                    bcdiv(
                        bcmul((string) $line->base_quantity, (string) $order->planned_quantity, 8),
                        (string) $recipe->output_quantity,
                        8,
                    ),
                    '0',
                    3,
                );

                if ((int) $component->unit_id !== (int) $line->unit_id
                    || bccomp((string) $component->conversion_factor, (string) $line->conversion_factor, 6) !== 0
                    || bccomp((string) $component->planned_quantity, $expectedQuantity, 3) !== 0
                    || bccomp((string) $component->planned_base_quantity, $expectedBase, 3) !== 0) {
                    $mismatches[] = [
                        'production_order_id' => $order->id,
                        'production_order_component_id' => $component->id,
                        'reason' => 'order_recipe_snapshot_mismatch',
                    ];
                }
            }
        }

        return new IntegrityResult(
            checked: $recipes->count()
                + $recipes->sum(fn ($recipe): int => $recipe->lines->count())
                + $orders->count()
                + $orders->sum(fn ($order): int => $order->components->count()),
            mismatches: $mismatches,
            durationMs: $this->elapsed($started),
        );
    }

    private function elapsed(int $started): int
    {
        return (int) round((hrtime(true) - $started) / 1_000_000);
    }
}

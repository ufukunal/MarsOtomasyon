<?php

namespace App\Support\Integrity\Checks;

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

        return new IntegrityResult(
            checked: $recipes->count() + $recipes->sum(fn ($recipe): int => $recipe->lines->count()),
            mismatches: $mismatches,
            durationMs: $this->elapsed($started),
        );
    }

    private function elapsed(int $started): int
    {
        return (int) round((hrtime(true) - $started) / 1_000_000);
    }
}

<?php

namespace App\Actions\Production;

use App\Enums\ProductKind;
use App\Models\Period\Product;
use App\Models\Period\ProductionRecipe;
use App\Models\Period\ProductionRecipeLine;
use App\Support\Audit\AuditContext;
use App\Support\Auth\MutationAuthorizer;
use App\Support\Period\PeriodContext;
use App\Support\Units\UnitConversionResolver;
use DomainException;
use Illuminate\Support\Facades\DB;

final class CreateProductionRecipeRevision
{
    public function __construct(private readonly UnitConversionResolver $units) {}

    /** @param list<array{component_product_id:int,unit_id:int,quantity:string}> $lines */
    public function handle(
        int $productId,
        string $outputQuantity,
        array $lines,
    ): ProductionRecipe {
        $hasRecipe = ProductionRecipe::query()->where('product_id', $productId)->exists();
        MutationAuthorizer::authorize('production_recipes.'.($hasRecipe ? 'update' : 'create'));
        PeriodContext::ensureWritable();

        $outputQuantity = bcadd($outputQuantity, '0', 3);

        if (bccomp($outputQuantity, '0', 3) <= 0 || $lines === []) {
            throw new DomainException('Reçete çıktı miktarı ve en az bir component satırı zorunludur.');
        }

        return DB::connection('period')->transaction(function () use ($productId, $outputQuantity, $lines): ProductionRecipe {
            $product = Product::query()->lockForUpdate()->findOrFail($productId);

            if ($product->kind === ProductKind::Set) {
                throw new DomainException('Set ürün için fiziksel üretim reçetesi oluşturulamaz.');
            }

            $normalized = [];
            $componentIds = [];

            foreach ($lines as $line) {
                $componentId = (int) $line['component_product_id'];

                if ($componentId === $productId || isset($componentIds[$componentId])) {
                    throw new DomainException('Reçetede aynı component tekrarlanamaz ve mamul kendi componenti olamaz.');
                }

                $component = Product::query()->findOrFail($componentId);

                if ($component->kind === ProductKind::Set) {
                    throw new DomainException('Set ürün reçete componenti olamaz.');
                }

                $quantity = bcadd((string) $line['quantity'], '0', 3);

                if (bccomp($quantity, '0', 3) <= 0) {
                    throw new DomainException('Reçete component miktarı pozitif olmalıdır.');
                }

                $unitId = (int) $line['unit_id'];
                $factor = $this->units->factor($unitId, (int) $component->unit_id);
                $baseQuantity = bcadd(bcmul($quantity, $factor, 8), '0', 3);

                if (bccomp($baseQuantity, '0', 3) <= 0) {
                    throw new DomainException('Reçete component temel miktarı pozitif olmalıdır.');
                }

                $componentIds[$componentId] = true;
                $normalized[] = [
                    'component_product_id' => $componentId,
                    'unit_id' => $unitId,
                    'quantity' => $quantity,
                    'base_quantity' => $baseQuantity,
                    'conversion_factor' => $factor,
                ];
            }

            $recipes = ProductionRecipe::query()
                ->where('product_id', $productId)
                ->orderBy('revision_no')
                ->lockForUpdate()
                ->get();

            foreach ($recipes->where('is_active', true) as $active) {
                $active->is_active = false;
                $active->version = (int) $active->version + 1;
                $active->save();
            }

            $revisionNo = ((int) $recipes->max('revision_no')) + 1;
            $actor = auth()->user();
            $recipe = ProductionRecipe::query()->create([
                'product_id' => $productId,
                'number' => 'REC-'.substr((string) $product->code, 0, 36),
                'revision_no' => $revisionNo,
                'output_quantity' => $outputQuantity,
                'is_active' => true,
                'version' => 1,
                'created_by' => $actor?->id,
                'created_by_name' => $actor?->name,
            ]);

            foreach ($normalized as $line) {
                ProductionRecipeLine::query()->create([
                    ...$line,
                    'production_recipe_id' => $recipe->id,
                    'version' => 1,
                ]);
            }

            AuditContext::period(
                'Üretim reçetesi revizyonu oluşturuldu.',
                [
                    'recipe_id' => $recipe->id,
                    'product_id' => $productId,
                    'revision_no' => $revisionNo,
                    'line_count' => count($normalized),
                ],
                $recipe,
                'production_recipe_revision_created',
            );

            return $recipe->load(['product', 'lines.componentProduct', 'lines.unit']);
        }, attempts: 3);
    }
}

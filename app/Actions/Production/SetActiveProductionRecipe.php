<?php

namespace App\Actions\Production;

use App\Models\Period\ProductionRecipe;
use App\Support\Audit\AuditContext;
use App\Support\Auth\MutationAuthorizer;
use DomainException;
use Illuminate\Support\Facades\DB;

final class SetActiveProductionRecipe
{
    public function handle(ProductionRecipe $recipe): ProductionRecipe
    {
        MutationAuthorizer::authorize('production_recipes.update');

        return DB::connection('period')->transaction(function () use ($recipe): ProductionRecipe {
            $target = ProductionRecipe::query()->lockForUpdate()->findOrFail($recipe->id);
            $siblings = ProductionRecipe::query()
                ->where('product_id', $target->product_id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($siblings->isEmpty()) {
                throw new DomainException('Aktifleştirilecek reçete bulunamadı.');
            }

            foreach ($siblings as $candidate) {
                $shouldBeActive = (int) $candidate->id === (int) $target->id;

                if ((bool) $candidate->is_active === $shouldBeActive) {
                    continue;
                }

                $candidate->is_active = $shouldBeActive;
                $candidate->version = (int) $candidate->version + 1;
                $candidate->save();
            }

            AuditContext::period(
                'Üretim reçetesi aktif revizyonu değiştirildi.',
                ['recipe_id' => $target->id, 'product_id' => $target->product_id],
                $target,
                'production_recipe_activated',
            );

            return $target->refresh()->load('lines');
        }, attempts: 3);
    }
}

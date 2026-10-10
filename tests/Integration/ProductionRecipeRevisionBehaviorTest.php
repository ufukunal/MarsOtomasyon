<?php

use App\Actions\Production\CreateProductionRecipeRevision;
use App\Actions\Production\SetActiveProductionRecipe;
use App\Models\Period\ProductionRecipe;
use Illuminate\Support\Facades\DB;
use Tests\Support\AuthorizedPeriod;
use Tests\Support\IsolatedPostgres;

it('creates revisions, freezes quantities and keeps exactly one active recipe per product', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();

        try {
            $target = IsolatedPostgres::productAndLocation();
            $component = IsolatedPostgres::productAndLocation();
            $action = app(CreateProductionRecipeRevision::class);
            $line = [
                'component_product_id' => $component['product'],
                'unit_id' => $component['unit'],
                'quantity' => '2.000',
            ];
            $first = $action->handle($target['product'], '1.000', [$line]);
            expect((int) $first->revision_no)->toBe(1)
                ->and($first->is_active)->toBeTrue();

            $second = $action->handle($target['product'], '3.000', [
                [...$line, 'quantity' => '6.000'],
            ]);
            expect((int) $second->revision_no)->toBe(2)
                ->and($first->fresh()->is_active)->toBeFalse()
                ->and($second->is_active)->toBeTrue();

            $reactivated = app(SetActiveProductionRecipe::class)->handle($first);
            expect($reactivated->is_active)->toBeTrue()
                ->and($second->fresh()->is_active)->toBeFalse();

            expect(DB::connection('period')->table('production_recipes')
                ->where('product_id', $target['product'])->where('is_active', true)->count())->toBe(1);
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});

it('rejects empty and zero-yield production recipes before creating rows', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();

        try {
            $target = IsolatedPostgres::productAndLocation();
            $action = app(CreateProductionRecipeRevision::class);
            expect(fn () => $action->handle($target['product'], '1.000', []))
                ->toThrow(DomainException::class);
            expect(fn () => $action->handle($target['product'], '0', [['component_product_id' => 1]]))
                ->toThrow(DomainException::class);
            expect(ProductionRecipe::query()->where('product_id', $target['product'])->count())->toBe(0);
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});

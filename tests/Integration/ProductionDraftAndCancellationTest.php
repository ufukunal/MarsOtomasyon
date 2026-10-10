<?php

use App\Actions\Production\CancelProductionOrderRemaining;
use App\Actions\Production\ConfirmProductionOrder;
use App\Actions\Production\PostProductionCompletion;
use App\Actions\Production\SaveProductionOrderDraft;
use App\Models\Period\ProductionOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\AuthorizedPeriod;
use Tests\Support\IsolatedPostgres;

function marsV4ProductionRecipe(): array
{
    $ids = IsolatedPostgres::productAndLocation();
    $recipeId = DB::connection('period')->table('production_recipes')->insertGetId([
        'product_id' => $ids['product'],
        'number' => 'R-'.Str::random(12),
        'revision_no' => 1,
        'output_quantity' => '1.000',
        'is_active' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return [$ids['product'], $recipeId];
}

it('creates production drafts with a frozen recipe revision and prevents a zero production plan', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();

        try {
            [$productId, $recipeId] = marsV4ProductionRecipe();
            $values = [
                'product_id' => $productId, 'recipe_id' => $recipeId,
                'planned_quantity' => '5.000', 'document_date' => '2026-10-10',
                'production_type' => 'internal',
            ];

            $draft = app(SaveProductionOrderDraft::class)->handle($values);
            expect($draft->status)->toBe('draft')
                ->and($draft->planned_quantity)->toBe('5.000')
                ->and((int) $draft->recipe_revision_no)->toBe(1);

            expect(fn () => app(SaveProductionOrderDraft::class)->handle([
                ...$values, 'planned_quantity' => '0',
            ]))->toThrow(DomainException::class);

            $cancelled = app(CancelProductionOrderRemaining::class)->handle($draft, 'v4-'.Str::random(20));
            expect($cancelled->status)->toBe('cancelled')
                ->and($cancelled->cancelled_quantity)->toBe('5.000');

            expect(fn () => app(CancelProductionOrderRemaining::class)->handle(
                $cancelled, 'v4-'.Str::random(20),
            ))->toThrow(DomainException::class);
            expect(DB::connection('period')->table('production_orders')->whereKey($draft->id)->count())->toBe(1);
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});

it('refuses production confirmation without recipe components and completion of a draft', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();

        try {
            [$productId, $recipeId] = marsV4ProductionRecipe();
            $draft = app(SaveProductionOrderDraft::class)->handle([
                'product_id' => $productId, 'recipe_id' => $recipeId,
                'planned_quantity' => '2.000', 'document_date' => '2026-10-10',
            ]);
            expect(fn () => app(ConfirmProductionOrder::class)->handle(
                $draft, 'v4-'.Str::random(20),
            ))->toThrow(DomainException::class);

            expect(fn () => app(PostProductionCompletion::class)->handle(
                $draft, '2026-10-10', '1.000', [], [], 'v4-'.Str::random(20),
            ))->toThrow(DomainException::class);

            expect(ProductionOrder::query()->findOrFail($draft->id)->status)->toBe('draft');
            expect(DB::connection('period')->table('production_completions')->count())->toBe(0);
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});

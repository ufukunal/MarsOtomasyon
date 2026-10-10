<?php

use App\Actions\Production\SendMaterialsToSubcontractor;
use App\Models\Period\ProductionOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\AuthorizedPeriod;
use Tests\Support\IsolatedPostgres;

it('does not ship inventory on behalf of an internal production order', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();

        try {
            $ids = IsolatedPostgres::productAndLocation();
            $recipeId = DB::connection('period')->table('production_recipes')->insertGetId([
                'product_id' => $ids['product'],
                'number' => 'V4-R-'.Str::random(8),
                'revision_no' => 1,
                'output_quantity' => '1.000',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $orderId = DB::connection('period')->table('production_orders')->insertGetId([
                'product_id' => $ids['product'],
                'recipe_id' => $recipeId,
                'recipe_revision_no' => 1,
                'document_date' => '2026-10-10',
                'planned_quantity' => '2.000',
                'production_type' => 'internal',
                'status' => 'confirmed',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            expect(fn () => app(SendMaterialsToSubcontractor::class)->handle(
                ProductionOrder::query()->findOrFail($orderId),
                $ids['location'],
                [],
                '2026-10-10',
                'v4-'.Str::random(20),
            ))->toThrow(DomainException::class);

            expect(DB::connection('period')->table('transfers')->count())->toBe(0);
            expect(DB::connection('period')->table('stock_movements')->count())->toBe(0);
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});

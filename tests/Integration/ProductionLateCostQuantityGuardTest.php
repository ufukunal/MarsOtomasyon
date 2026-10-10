<?php

use App\Actions\Production\ApplyInventoryCostAdjustment;
use Illuminate\Support\Facades\DB;
use Tests\Support\IsolatedPostgres;

it('rejects late subcontracting cost allocations with zero or negative output before changing the cost ledger', function (string $quantity): void {
    IsolatedPostgres::withActivePeriod(function () use ($quantity): void {
        $before = DB::connection('period')->table('inventory_cost_adjustments')->count();
        expect(fn () => app(ApplyInventoryCostAdjustment::class)->handle(
            1, 1, 1, '2026-10-10', $quantity, '20.0000',
        ))->toThrow(DomainException::class);

        expect(DB::connection('period')->table('inventory_cost_adjustments')->count())->toBe($before)
            ->and(DB::connection('period')->table('product_costs')->count())->toBe(0);
    });
})->with(['0', '-1.000', '-0.001']);

<?php

use App\Actions\Stock\UpdateMovingAverage;
use Illuminate\Support\Facades\DB;
use Tests\Support\IsolatedPostgres;

function marsV4CostFixture(string $quantity = '4.000', string $average = '10.0000'): array
{
    $ids = IsolatedPostgres::productAndLocation();
    $db = DB::connection('period');

    $db->table('stock_balances')->insert([
        'product_id' => $ids['product'],
        'location_id' => $ids['location'],
        'quantity' => $quantity,
        'reserved' => '0.000',
        'quarantine' => '0.000',
        'consignment_reserved' => '0.000',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $db->table('product_costs')->insert([
        'product_id' => $ids['product'],
        'last_purchase_price' => $average,
        'moving_average' => $average,
        'import_cost' => '0.0000',
        'production_cost' => '0.0000',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $ids;
}

it('weights incoming costs against existing physical quantity at four-decimal precision', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        $ids = marsV4CostFixture();
        $average = app(UpdateMovingAverage::class)->handle(
            $ids['product'], '2.000', '20.0000', 'purchase', '2026-10-10',
        );

        expect($average)->toBe('13.3333');

        $row = DB::connection('period')->table('product_costs')
            ->where('product_id', $ids['product'])->first();
        expect((string) $row->last_purchase_price)->toBe('20.0000')
            ->and((string) $row->moving_average)->toBe('13.3333');
    });
});

it('applies positive and negative landed-cost value adjustments without changing physical stock', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        $ids = marsV4CostFixture();
        $action = app(UpdateMovingAverage::class);

        expect($action->applyValueDelta($ids['product'], '8.0000'))->toBe('12.0000');
        expect($action->applyValueDelta($ids['product'], '-4.0000'))->toBe('11.0000');

        expect((string) DB::connection('period')->table('stock_balances')
            ->where('product_id', $ids['product'])->value('quantity'))->toBe('4.000');
    });
});

it('refuses a cost reversal that would make total remaining inventory value negative', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        $ids = marsV4CostFixture();

        expect(fn () => app(UpdateMovingAverage::class)->applyValueDelta(
            $ids['product'], '-40.0001',
        ))->toThrow(DomainException::class);

        expect((string) DB::connection('period')->table('product_costs')
            ->where('product_id', $ids['product'])->value('moving_average'))->toBe('10.0000');
    });
});

it('uses purchase cost directly as the average when available physical stock is zero', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        $ids = marsV4CostFixture('0.000');

        expect(app(UpdateMovingAverage::class)->handle(
            $ids['product'], '2.000', '27.4000',
        ))->toBe('27.4000');
    });
});

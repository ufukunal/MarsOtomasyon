<?php

use App\Actions\Purchases\CheckPurchasePriceDeviation;
use Illuminate\Support\Facades\DB;
use Tests\Support\IsolatedPostgres;

it('flags deviations above twenty-five percent in either direction', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        $fixture = IsolatedPostgres::productAndLocation();
        DB::connection('period')->table('product_costs')->insert([
            'product_id' => $fixture['product'],
            'last_purchase_price' => '100.0000',
            'moving_average' => '100.0000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $check = new CheckPurchasePriceDeviation;
        $near = $check->handle($fixture['product'], '125');
        $above = $check->handle($fixture['product'], '126');
        $below = $check->handle($fixture['product'], '74');

        expect($near->deviationRate)->toBe('25.0000')
            ->and($near->exceedsThreshold)->toBeFalse()
            ->and($above->deviationRate)->toBe('26.0000')
            ->and($above->exceedsThreshold)->toBeTrue()
            ->and($below->deviationRate)->toBe('-26.0000')
            ->and($below->exceedsThreshold)->toBeTrue();
    });
});

it('does not invent a deviation when no previous supplier price exists', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        $fixture = IsolatedPostgres::productAndLocation();
        $result = (new CheckPurchasePriceDeviation)->handle($fixture['product'], '30');

        expect($result->previousPrice)->toBe('0.0000')
            ->and($result->deviationRate)->toBe('0.0000')
            ->and($result->exceedsThreshold)->toBeFalse();
    });
});

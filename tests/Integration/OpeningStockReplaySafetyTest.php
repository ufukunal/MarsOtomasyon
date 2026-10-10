<?php

use App\Actions\Stock\ImportOpeningStock;
use Illuminate\Support\Facades\DB;
use Tests\Support\AuthorizedPeriod;
use Tests\Support\IsolatedPostgres;

it('imports a valid opening quantity and refuses another batch for the same accounting period', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();

        try {
            $ids = IsolatedPostgres::productAndLocation();
            $db = DB::connection('period');
            $product = (string) $db->table('products')->where('id', $ids['product'])->value('code');
            $location = (string) $db->table('locations')->where('id', $ids['location'])->value('code');

            app(ImportOpeningStock::class)->handle([
                [
                    'product_code' => $product,
                    'location_code' => $location,
                    'quantity' => '5.000',
                    'unit_cost' => '7.5000',
                ],
            ], '2026-10-10', 'V4-OPEN-1', null, null);

            expect((string) $db->table('stock_balances')
                ->where('product_id', $ids['product'])
                ->where('location_id', $ids['location'])
                ->value('quantity'))->toBe('5.000');

            expect(fn () => app(ImportOpeningStock::class)->handle([
                [
                    'product_code' => $product,
                    'location_code' => $location,
                    'quantity' => '1.000',
                    'unit_cost' => '7.5000',
                ],
            ], '2026-10-10', 'V4-OPEN-2', null, null))->toThrow(DomainException::class);

            expect($db->table('stock_movements')->where('reason', 'opening')->count())->toBe(1);
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});

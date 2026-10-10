<?php

use App\Actions\Stock\AdjustQuarantineBalance;
use Illuminate\Support\Facades\DB;
use Tests\Support\IsolatedPostgres;

it('tracks incremental quarantine allocations without altering on-hand stock quantity', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        $ids = IsolatedPostgres::productAndLocation();
        DB::connection('period')->table('stock_balances')->insert([
            'product_id' => $ids['product'],
            'location_id' => $ids['location'],
            'quantity' => '5.000',
            'reserved' => '0.000',
            'consignment_reserved' => '0.000',
            'quarantine' => '0.000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $action = app(AdjustQuarantineBalance::class);
        expect((string) $action->handle($ids['product'], $ids['location'], '3.000')->quarantine)->toBe('3.000');
        expect((string) $action->handle($ids['product'], $ids['location'], '-2.000')->quarantine)->toBe('1.000');

        foreach (['-1.001', '4.001'] as $invalidDelta) {
            expect(fn () => $action->handle(
                $ids['product'], $ids['location'], $invalidDelta,
            ))->toThrow(DomainException::class);
        }

        $stored = DB::connection('period')->table('stock_balances')
            ->where('product_id', $ids['product'])->first();

        expect((string) $stored->quantity)->toBe('5.000')
            ->and((string) $stored->quarantine)->toBe('1.000');
    });
});

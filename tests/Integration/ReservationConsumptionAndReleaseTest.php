<?php

use App\Actions\Stock\AdjustReservedBalance;
use App\Actions\Stock\ConsumeReservation;
use App\Actions\Stock\ReleaseReservation;
use App\Models\Period\StockReservation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\AuthorizedPeriod;
use Tests\Support\IsolatedPostgres;

function marsV4ReservedStock(): StockReservation
{
    $ids = IsolatedPostgres::productAndLocation();
    DB::connection('period')->table('stock_balances')->insert([
        'product_id' => $ids['product'],
        'location_id' => $ids['location'],
        'quantity' => '10.000',
        'reserved' => '0.000',
        'consignment_reserved' => '0.000',
        'quarantine' => '0.000',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    app(AdjustReservedBalance::class)->handle($ids['product'], $ids['location'], '6.000');

    return StockReservation::query()->create([
        'product_id' => $ids['product'],
        'location_id' => $ids['location'],
        'quantity' => '6.000',
        'document_type' => 'sales_order',
        'document_id' => null,
        'status' => 'active',
    ]);
}

it('partially consumes and releases a stock reservation without exceeding physical inventory', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();

        try {
            $reservation = marsV4ReservedStock();
            $consumed = app(ConsumeReservation::class)->handle(
                $reservation->id, 'v4-'.Str::random(18), '2.000',
            );

            expect($consumed->status)->toBe('consumed')
                ->and((string) $consumed->quantity)->toBe('2.000')
                ->and((string) $reservation->fresh()->quantity)->toBe('4.000');

            $released = app(ReleaseReservation::class)->handle(
                $reservation->id, 'v4-'.Str::random(18), '1.000',
            );
            expect($released->status)->toBe('released')
                ->and((string) $reservation->fresh()->quantity)->toBe('3.000');

            $remaining = app(ReleaseReservation::class)->handle(
                $reservation->id, 'v4-'.Str::random(18),
            );
            expect($remaining->status)->toBe('released');

            $balance = DB::connection('period')->table('stock_balances')
                ->where('product_id', $reservation->product_id)
                ->where('location_id', $reservation->location_id)->first();

            expect((string) $balance->reserved)->toBe('0.000');
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});

it('rejects zero, negative and excessive reservation consumption without changing the reserved balance', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();

        try {
            $reservation = marsV4ReservedStock();
            foreach (['0', '-1.000', '7.000'] as $amount) {
                expect(fn () => app(ConsumeReservation::class)->handle(
                    $reservation->id, 'v4-'.Str::random(18), $amount,
                ))->toThrow(DomainException::class);
            }

            expect((string) $reservation->fresh()->quantity)->toBe('6.000');
            expect((string) DB::connection('period')->table('stock_balances')
                ->where('product_id', $reservation->product_id)
                ->where('location_id', $reservation->location_id)
                ->value('reserved'))->toBe('6.000');
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});

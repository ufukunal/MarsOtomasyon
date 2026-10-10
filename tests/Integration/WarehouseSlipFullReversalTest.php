<?php

use App\Actions\Stock\PostWarehouseSlip;
use App\Actions\Stock\ReverseWarehouseSlip;
use App\Actions\Stock\SaveWarehouseSlipDraft;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\AuthorizedPeriod;
use Tests\Support\IsolatedPostgres;

it('posts an inbound warehouse slip, reverses it, and restores physical balance to zero', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();

        try {
            $ids = IsolatedPostgres::productAndLocation();
            $draft = app(SaveWarehouseSlipDraft::class)->handle([
                'location_id' => $ids['location'],
                'slip_date' => '2026-10-10',
                'direction' => 'in',
                'reason' => 'found',
                'lines' => [
                    ['product_id' => $ids['product'], 'quantity' => '3.000', 'unit_cost' => null],
                ],
            ]);
            expect($draft->status)->toBe('draft');

            $posted = app(PostWarehouseSlip::class)->handle($draft->id, 'v4-'.Str::random(18));
            expect($posted->status)->toBe('posted')
                ->and($posted->number)->not->toBeNull();

            $reversed = app(ReverseWarehouseSlip::class)->handle($posted->id, 'v4-'.Str::random(18));
            expect($reversed->status)->toBe('posted')
                ->and($posted->fresh()->status)->toBe('cancelled');

            $balance = DB::connection('period')->table('stock_balances')
                ->where('product_id', $ids['product'])
                ->where('location_id', $ids['location'])
                ->first();

            expect((string) $balance->quantity)->toBe('0.000')
                ->and(DB::connection('period')->table('stock_movements')
                    ->where('product_id', $ids['product'])->count())->toBe(2);
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});

it('rejects reversing an unposted warehouse slip without changing stock', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();

        try {
            $ids = IsolatedPostgres::productAndLocation();
            $draft = app(SaveWarehouseSlipDraft::class)->handle([
                'location_id' => $ids['location'],
                'slip_date' => '2026-10-10',
                'direction' => 'out',
                'reason' => 'scrap',
                'lines' => [
                    ['product_id' => $ids['product'], 'quantity' => '1.000', 'unit_cost' => null],
                ],
            ]);

            expect(fn () => app(ReverseWarehouseSlip::class)->handle(
                $draft->id, 'v4-'.Str::random(18),
            ))->toThrow(DomainException::class);

            expect(DB::connection('period')->table('stock_movements')->count())->toBe(0);
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});

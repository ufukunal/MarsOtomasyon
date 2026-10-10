<?php

use App\Actions\Stock\ReceiveToQuarantine;
use App\Actions\Stock\ReleaseQuarantine;
use App\Actions\Stock\ScrapQuarantine;
use App\DataObjects\QuarantineReceiptData;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\AuthorizedPeriod;
use Tests\Support\IsolatedPostgres;

it('quarantines five units, releases two, scraps three and retains correct physical stock', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();

        try {
            $ids = IsolatedPostgres::productAndLocation();
            $receipt = new QuarantineReceiptData(
                productId: $ids['product'],
                locationId: $ids['location'],
                movementDate: '2026-10-10',
                quantity: '5.000',
                unitCost: '8.0000',
            );

            $entry = app(ReceiveToQuarantine::class)->handle($receipt, 'v4-'.Str::random(20));
            expect($entry->pendingQuantity())->toBe('5.000');

            $released = app(ReleaseQuarantine::class)->handle($entry->id, '2.000', 'v4-'.Str::random(20));
            expect($released->pendingQuantity())->toBe('3.000');

            expect(fn () => app(ReleaseQuarantine::class)->handle(
                $entry->id, '4.000', 'v4-'.Str::random(20),
            ))->toThrow(DomainException::class);

            $scrapped = app(ScrapQuarantine::class)->handle($entry->id, '3.000', 'v4-'.Str::random(20));
            expect($scrapped->pendingQuantity())->toBe('0.000')
                ->and($scrapped->released_quantity)->toBe('2.000')
                ->and($scrapped->scrapped_quantity)->toBe('3.000');

            $balance = DB::connection('period')->table('stock_balances')
                ->where('product_id', $ids['product'])
                ->where('location_id', $ids['location'])
                ->first();
            expect((string) $balance->quantity)->toBe('2.000')
                ->and((string) $balance->quarantine)->toBe('0.000');
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});

it('refuses zero or negative quarantine decisions before creating ledger movements', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();

        try {
            foreach (['0', '-1'] as $quantity) {
                expect(fn () => app(ReleaseQuarantine::class)->handle(
                    1, $quantity, 'v4-'.Str::random(20),
                ))->toThrow(DomainException::class);
                expect(fn () => app(ScrapQuarantine::class)->handle(
                    1, $quantity, 'v4-'.Str::random(20),
                ))->toThrow(DomainException::class);
            }
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});

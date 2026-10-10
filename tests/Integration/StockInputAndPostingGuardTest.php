<?php

use App\Actions\Stock\ImportOpeningStock;
use App\Actions\Stock\PostStockCount;
use App\Actions\Stock\SaveTransferDraft;
use App\Actions\Stock\SaveWarehouseSlipDraft;
use App\Models\Period\StockCount;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Support\AuthorizedPeriod;
use Tests\Support\IsolatedPostgres;

it('refuses importing an empty opening stock batch before ledger changes', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();

        try {
            expect(fn () => app(ImportOpeningStock::class)->handle(
                [], '2026-10-10', 'v4-'.Str::random(12), null, null,
            ))->toThrow(DomainException::class);
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});

it('rejects a transfer draft between the same location before creating a document', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();

        try {
            $ids = IsolatedPostgres::productAndLocation();

            expect(fn () => app(SaveTransferDraft::class)->handle([
                'from_location_id' => $ids['location'],
                'to_location_id' => $ids['location'],
                'transfer_date' => '2026-10-10',
                'lines' => [['product_id' => $ids['product'], 'quantity' => '1.000']],
            ]))->toThrow(ValidationException::class);
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});

it('rejects incompatible warehouse slip movement directions and reasons', function (string $direction, string $reason): void {
    IsolatedPostgres::withActivePeriod(function () use ($direction, $reason): void {
        AuthorizedPeriod::login();

        try {
            $ids = IsolatedPostgres::productAndLocation();

            expect(fn () => app(SaveWarehouseSlipDraft::class)->handle([
                'location_id' => $ids['location'],
                'slip_date' => '2026-10-10',
                'direction' => $direction,
                'reason' => $reason,
                'lines' => [],
            ]))->toThrow(ValidationException::class);
        } finally {
            AuthorizedPeriod::logout();
        }
    });
})->with([
    ['sideways', 'adjustment'],
    ['in', 'scrap'],
    ['out', 'found'],
]);

it('refuses to post stock counts which have not been reviewed', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();

        try {
            $ids = IsolatedPostgres::productAndLocation();
            $count = StockCount::query()->create([
                'location_id' => $ids['location'],
                'count_date' => '2026-10-10',
                'status' => 'draft',
            ]);

            expect(fn () => app(PostStockCount::class)->handle($count->id, 'v4-'.Str::random(18)))
                ->toThrow(DomainException::class);
            expect($count->fresh()->status)->toBe('draft');
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});

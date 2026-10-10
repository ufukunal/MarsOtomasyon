<?php

use App\Actions\Pricing\BulkAdjustPriceList;
use App\Actions\Pricing\SavePriceList;
use App\Actions\Pricing\SavePriceListItem;
use App\Models\Period\Product;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Support\AuthorizedPeriod;
use Tests\Support\IsolatedPostgres;

it('applies a bounded bulk change to all list rows exactly once', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();

        try {
            $list = app(SavePriceList::class)->handle(['name' => 'V4 Bulk '.Str::random(6)]);
            $sku = IsolatedPostgres::productAndLocation();
            $product = Product::query()->findOrFail($sku['product']);
            app(SavePriceListItem::class)->handle($list, $product, '100', '2026-01-01', '2026-12-31');

            expect(app(BulkAdjustPriceList::class)->handle($list, '25'))->toBe(1);

            $row = $list->items()->firstOrFail();
            expect((string) $row->price)->toBe('125.0000');
            expect(app(BulkAdjustPriceList::class)->handle($list, '-20'))->toBe(1);
            expect((string) $row->fresh()->price)->toBe('100.0000');
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});

it('rejects a bulk change below minus one hundred percent without modifying prices', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();

        try {
            $list = app(SavePriceList::class)->handle(['name' => 'V4 Safe '.Str::random(6)]);
            expect(fn () => app(BulkAdjustPriceList::class)->handle($list, '-100.0001'))
                ->toThrow(ValidationException::class);
            expect($list->items()->count())->toBe(0);
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});

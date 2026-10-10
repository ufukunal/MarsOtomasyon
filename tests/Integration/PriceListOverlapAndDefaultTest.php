<?php

use App\Actions\Pricing\SavePriceList;
use App\Actions\Pricing\SavePriceListItem;
use App\Models\Period\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Support\AuthorizedPeriod;
use Tests\Support\IsolatedPostgres;

it('converts VAT-inclusive prices to net and rejects overlapping product price windows', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();

        try {
            $ids = IsolatedPostgres::productAndLocation();
            $product = Product::query()->findOrFail($ids['product']);
            $product->vat_rate = '20';
            $product->save();

            $list = app(SavePriceList::class)->handle([
                'name' => 'V4 Price List', 'currency' => 'TRY', 'vat_included' => true,
            ]);
            expect($list->is_default)->toBeTrue();

            $action = app(SavePriceListItem::class);
            $first = $action->handle($list, $product, '120', '2026-01-01', '2026-06-30');
            expect($first->price)->toBe('100.0000');

            expect(fn () => $action->handle($list, $product, '130', '2026-06-30', '2026-09-01'))
                ->toThrow(ValidationException::class);

            $later = $action->handle($list, $product, '240', '2026-07-01', '2026-12-31');
            expect($later->price)->toBe('200.0000');
            expect(DB::connection('period')->table('price_list_items')->where('price_list_id', $list->id)->count())
                ->toBe(2);
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});

it('does not allow disabling or unselecting the only default price list', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();

        try {
            $list = app(SavePriceList::class)->handle(['name' => 'V4 Default']);
            expect(fn () => app(SavePriceList::class)->handle([
                'name' => 'V4 Default', 'is_default' => false, 'is_active' => false,
            ], $list, (int) $list->version))->toThrow(ValidationException::class);
            expect($list->refresh()->is_default)->toBeTrue()
                ->and($list->is_active)->toBeTrue();
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});

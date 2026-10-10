<?php

use App\Actions\Catalog\SaveBrand;
use App\Actions\Products\SaveConfigDefinition;
use App\Actions\Products\SaveProduct;
use App\Actions\Products\SaveSetComponent;
use App\Models\Period\Product;
use App\Models\Period\Unit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Support\AuthorizedPeriod;
use Tests\Support\IsolatedPostgres;

it('creates products with normalized codes and net-of-VAT prices', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();

        try {
            $unit = Unit::query()->create(['code' => 'U'.Str::random(8), 'name' => 'Test Unit']);
            $product = app(SaveProduct::class)->handle([
                'code' => ' abc-123 ',
                'name' => 'V4 Product',
                'unit_id' => $unit->id,
                'list_price' => '120.0000',
                'vat_rate' => '20',
                'price_vat_included' => true,
                'kind' => 'normal',
            ]);
            expect($product->code)->toBe('ABC-123')
                ->and($product->list_price)->toBe('100.0000')
                ->and(Product::query()->whereKey($product->id)->count())->toBe(1);

            $updated = app(SaveProduct::class)->handle([
                'code' => 'HACKED',
                'name' => 'V4 Updated',
                'unit_id' => $unit->id,
                'list_price' => '100',
                'vat_rate' => '20',
            ], $product, (int) $product->version);
            expect($updated->code)->toBe('ABC-123')
                ->and($updated->name)->toBe('V4 Updated');
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});

it('rejects an invalid VAT-inclusive pricing factor before inserting a product', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();

        try {
            $before = DB::connection('period')->table('products')->count();
            expect(fn () => app(SaveProduct::class)->handle([
                'code' => 'REJECT',
                'name' => 'Invalid VAT Product',
                'unit_id' => 1,
                'vat_rate' => '-100',
                'list_price' => '10',
                'price_vat_included' => true,
            ]))->toThrow(ValidationException::class);
            expect(DB::connection('period')->table('products')->count())->toBe($before);
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});

it('rejects invalid set components, and mandatory configurators without options', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();

        try {
            $normal = new Product(['kind' => 'normal']);
            $normal->id = 23;
            $set = new Product(['kind' => 'set']);
            $set->id = 24;

            expect(fn () => app(SaveSetComponent::class)->handle($normal, $set, '2'))
                ->toThrow(ValidationException::class);
            expect(fn () => app(SaveSetComponent::class)->handle($set, $set, '2'))
                ->toThrow(ValidationException::class);
            expect(fn () => app(SaveSetComponent::class)->handle($set, $normal, '0'))
                ->toThrow(ValidationException::class);

            expect(fn () => app(SaveConfigDefinition::class)->handle(
                $normal, ['name' => 'Color', 'is_required' => true], [],
            ))->toThrow(ValidationException::class);

            expect(fn () => app(SaveConfigDefinition::class)->handle(
                $normal, ['name' => 'Color'], [['is_default' => true], ['is_default' => true]],
            ))->toThrow(ValidationException::class);
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});

it('trims new brand names without creating duplicate implicit records', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();

        try {
            $brand = app(SaveBrand::class)->handle(['name' => '  V4 Test Brand  ']);
            expect($brand->name)->toBe('V4 Test Brand')
                ->and($brand->is_active)->toBeTrue();
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});

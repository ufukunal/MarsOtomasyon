<?php

use App\Actions\Catalog\SaveProductCategory;
use App\Actions\Products\SaveProduct;
use App\Models\Period\PriceListItem;
use App\Models\Period\ProductCategory;
use App\Models\Period\Unit;
use App\Support\Pricing\PriceResolver;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    [$company, $period] = $this->createCompanyWithPeriod('V2CATALOG');
    $admin = $this->createUserWithPeriodAccess($company, $period, 'Yönetici');
    $this->loginToPeriod($admin, $company, $period);
});

it('v2 product save converts VAT-inclusive price into a precise tax-exclusive list price', function () {
    $unit = Unit::query()->where('code', 'ADET')->firstOrFail();
    $product = app(SaveProduct::class)->handle([
        'code' => 'v2-inclusive', 'name' => 'VAT test',
        'unit_id' => $unit->id, 'vat_rate' => '20', 'list_price' => '120.0000',
        'price_vat_included' => true,
    ]);
    expect($product->code)->toBe('V2-INCLUSIVE')
        ->and((string) $product->list_price)->toBe('100.0000');
});

it('v2 product update ignores an attempted product code rewrite and retains identity', function () {
    $unit = Unit::query()->where('code', 'ADET')->firstOrFail();
    $product = $this->createTestProduct(['code' => 'V2-IMMUTABLE']);
    $saved = app(SaveProduct::class)->handle([
        'code' => 'MALICIOUS', 'name' => 'New Name', 'unit_id' => $unit->id,
        'vat_rate' => '20', 'list_price' => '125',
    ], $product, $product->version);
    expect($saved->fresh()->code)->toBe('V2-IMMUTABLE')
        ->and($saved->fresh()->name)->toBe('New Name');
});

it('v2 price resolver falls back to the product snapshot without a matching price list', function () {
    $product = $this->createTestProduct(['list_price' => '19.1234']);
    expect(app(PriceResolver::class)->resolve($product))->toBe('19.1234');
});

it('v2 price resolver obeys the default active list and valid date', function () {
    $product = $this->createTestProduct(['list_price' => '11.0000']);
    $list = $this->createTestPriceList(['is_default' => true]);
    PriceListItem::query()->create([
        'price_list_id' => $list->id, 'product_id' => $product->id,
        'price' => '22.5000', 'valid_from' => '2026-09-01', 'valid_to' => '2026-09-30',
    ]);
    $resolver = app(PriceResolver::class);
    expect($resolver->resolve($product, null, CarbonImmutable::parse('2026-09-15')))->toBe('22.5000')
        ->and($resolver->resolve($product, null, CarbonImmutable::parse('2026-10-01')))->toBe('11.0000');
});

it('v2 product category forbids becoming its own parent', function () {
    $category = app(SaveProductCategory::class)->handle(['name' => 'Root']);
    expect(fn () => app(SaveProductCategory::class)->handle([
        'name' => 'Loop', 'parent_id' => $category->id,
    ], $category, $category->version))->toThrow(ValidationException::class);
    expect($category->refresh()->parent_id)->toBeNull();
});

it('v2 category hierarchy rejects levels deeper than three', function () {
    $writer = app(SaveProductCategory::class);
    $root = $writer->handle(['name' => 'Root']);
    $child = $writer->handle(['name' => 'Child', 'parent_id' => $root->id]);
    $grandchild = $writer->handle(['name' => 'Grandchild', 'parent_id' => $child->id]);
    expect(fn () => $writer->handle(['name' => 'Too Deep', 'parent_id' => $grandchild->id]))
        ->toThrow(ValidationException::class);
    expect(ProductCategory::query()->count())->toBe(3);
});

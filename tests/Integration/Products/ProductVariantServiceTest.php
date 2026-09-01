<?php

use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Tax;
use App\Modules\Products\Enums\ProductStatus;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\Unit;
use App\Modules\Products\Variants\ProductVariantService;
use DomainException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;

uses(DatabaseMigrations::class);

it('groups sellable SKU products without moving stock price barcode or cost authority', function (): void {
    $company = Company::query()->create([
        'code' => 'M25-VAR',
        'name' => 'M25 Variant Company',
        'status' => 'active',
        'base_currency_code' => 'TRY',
        'timezone' => 'Europe/Istanbul',
    ]);
    $companyId = (int) $company->getKey();
    $red = m25VariantProduct($company, 'SKU-RED');
    $blue = m25VariantProduct($company, 'SKU-BLUE');
    $simple = m25VariantProduct($company, 'SKU-SIMPLE');
    $service = app(ProductVariantService::class);

    $family = $service->createFamily($companyId, 'lamp-family', 'Lamp Family', ['description' => 'Shared family content']);
    $color = $service->addDimension($companyId, (int) $family->getKey(), 'color', 'Color', 0);
    $size = $service->addDimension($companyId, (int) $family->getKey(), 'size', 'Size', 1);
    $redValue = $service->addValue($companyId, (int) $color->getKey(), 'red', 'Red');
    $blueValue = $service->addValue($companyId, (int) $color->getKey(), 'blue', 'Blue');
    $smallValue = $service->addValue($companyId, (int) $size->getKey(), 'small', 'Small');

    $redRelation = $service->attachProduct($companyId, (int) $family->getKey(), (int) $red->getKey(), [
        (int) $size->getKey() => (int) $smallValue->getKey(),
        (int) $color->getKey() => (int) $redValue->getKey(),
    ]);
    $blueRelation = $service->attachProduct($companyId, (int) $family->getKey(), (int) $blue->getKey(), [
        (int) $color->getKey() => (int) $blueValue->getKey(),
        (int) $size->getKey() => (int) $smallValue->getKey(),
    ]);

    expect($red->getKey())->not->toBe($blue->getKey());
    expect($red->fresh()->sale_price_net)->toBe('100.000000');
    expect($simple->variantRelation()->exists())->toBeFalse();

    $mapId = $service->mapMarketplace($companyId, (int) $redRelation->getKey(), 'trendyol', 'PARENT-1', 'VARIANT-RED');
    expect($service->mapMarketplace($companyId, (int) $redRelation->getKey(), 'trendyol', 'PARENT-1', 'VARIANT-RED'))->toBe($mapId);
    expect((int) DB::table('marketplace_variant_mappings')->where('id', $mapId)->value('product_variant_relation_id'))
        ->toBe((int) $redRelation->getKey());
    expect($blueRelation->variant_signature)->not->toBe($redRelation->variant_signature);

    $family->delete();
    expect(Product::query()->whereKey($red->getKey())->exists())->toBeTrue();
    expect(Product::query()->whereKey($blue->getKey())->exists())->toBeTrue();
    expect(Product::query()->whereKey($simple->getKey())->exists())->toBeTrue();
});

it('rejects duplicate variant combinations inside the same family', function (): void {
    $company = Company::query()->create([
        'code' => 'M25-DUP',
        'name' => 'M25 Duplicate Company',
        'status' => 'active',
        'base_currency_code' => 'TRY',
        'timezone' => 'Europe/Istanbul',
    ]);
    $companyId = (int) $company->getKey();
    $one = m25VariantProduct($company, 'SKU-ONE');
    $two = m25VariantProduct($company, 'SKU-TWO');
    $service = app(ProductVariantService::class);
    $family = $service->createFamily($companyId, 'duplicate-family', 'Duplicate Family');
    $color = $service->addDimension($companyId, (int) $family->getKey(), 'color', 'Color');
    $red = $service->addValue($companyId, (int) $color->getKey(), 'red', 'Red');
    $assignment = [(int) $color->getKey() => (int) $red->getKey()];

    $service->attachProduct($companyId, (int) $family->getKey(), (int) $one->getKey(), $assignment);

    expect(fn () => $service->attachProduct($companyId, (int) $family->getKey(), (int) $two->getKey(), $assignment))
        ->toThrow(DomainException::class, 'already assigned');
});

function m25VariantProduct(Company $company, string $code): Product
{
    $category = Category::query()->create([
        'company_id' => $company->getKey(),
        'code' => $code.'-CAT',
        'name' => 'Category '.$code,
        'is_active' => true,
    ]);
    $unit = Unit::query()->create([
        'company_id' => $company->getKey(),
        'code' => mb_substr($code.'-UNIT', 0, 32),
        'name' => 'Unit '.$code,
        'is_active' => true,
    ]);
    $tax = Tax::query()->create([
        'company_id' => $company->getKey(),
        'code' => mb_substr($code.'-TAX', 0, 64),
        'name' => 'Tax '.$code,
        'rate' => '20.000000',
        'is_active' => true,
    ]);

    return Product::query()->create([
        'company_id' => $company->getKey(),
        'code' => $code,
        'status' => ProductStatus::Active,
        'name' => 'Product '.$code,
        'category_id' => $category->getKey(),
        'unit_id' => $unit->getKey(),
        'tax_id' => $tax->getKey(),
        'sale_price_net' => '100.000000',
        'purchase_price_net' => '50.000000',
    ]);
}

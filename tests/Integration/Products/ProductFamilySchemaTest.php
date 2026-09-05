<?php

use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Tax;
use App\Modules\Products\Enums\ProductStatus;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\ProductFamily;
use App\Modules\Products\Models\ProductVariantRelation;
use App\Modules\Products\Models\Unit;
use App\Modules\Products\Models\VariantDimension;
use App\Modules\Products\Models\VariantValue;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;

uses(DatabaseMigrations::class);

it('keeps product identity independent from family lifecycle', function (): void {
    $company = m25SchemaCompany('M25-ID');
    $product = m25SchemaProduct($company, 'SKU-KEEP');
    $originalId = (int) $product->getKey();
    $originalCode = $product->code;

    $family = ProductFamily::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'family-one',
        'name' => 'Family One',
    ]);
    ProductVariantRelation::query()->create([
        'company_id' => $company->getKey(),
        'product_family_id' => $family->getKey(),
        'product_id' => $product->getKey(),
        'variant_signature' => hash('sha256', '1:1'),
    ]);

    $family->delete();

    $product->refresh();
    expect((int) $product->getKey())->toBe($originalId)
        ->and($product->code)->toBe($originalCode)
        ->and($product->variantRelation()->exists())->toBeFalse();
});

it('rejects cross-company product family relations at the database boundary', function (): void {
    $companyA = m25SchemaCompany('M25-TA');
    $companyB = m25SchemaCompany('M25-TB');
    $family = ProductFamily::query()->create([
        'company_id' => $companyA->getKey(),
        'code' => 'tenant-family',
        'name' => 'Tenant Family',
    ]);
    $foreignProduct = m25SchemaProduct($companyB, 'SKU-FOREIGN');

    expect(fn () => DB::table('product_variant_relations')->insert([
        'company_id' => $companyA->getKey(),
        'product_family_id' => $family->getKey(),
        'product_id' => $foreignProduct->getKey(),
        'variant_signature' => hash('sha256', 'foreign'),
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('rejects duplicate dimensions values and wrong-dimension assignments', function (): void {
    $company = m25SchemaCompany('M25-INV');
    $product = m25SchemaProduct($company, 'SKU-INV');
    $family = ProductFamily::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'invariant-family',
        'name' => 'Invariant Family',
    ]);
    $color = VariantDimension::query()->create([
        'company_id' => $company->getKey(),
        'product_family_id' => $family->getKey(),
        'code' => 'color',
        'name' => 'Color',
        'position' => 0,
    ]);
    $size = VariantDimension::query()->create([
        'company_id' => $company->getKey(),
        'product_family_id' => $family->getKey(),
        'code' => 'size',
        'name' => 'Size',
        'position' => 1,
    ]);
    $red = VariantValue::query()->create([
        'company_id' => $company->getKey(),
        'product_family_id' => $family->getKey(),
        'variant_dimension_id' => $color->getKey(),
        'code' => 'red',
        'label' => 'Red',
        'position' => 0,
    ]);
    $relation = ProductVariantRelation::query()->create([
        'company_id' => $company->getKey(),
        'product_family_id' => $family->getKey(),
        'product_id' => $product->getKey(),
        'variant_signature' => hash('sha256', $size->getKey().':'.$red->getKey()),
    ]);

    expect(fn () => VariantDimension::query()->create([
        'company_id' => $company->getKey(),
        'product_family_id' => $family->getKey(),
        'code' => 'COLOR',
        'name' => 'Duplicate Color',
    ]))->toThrow(QueryException::class);

    expect(fn () => VariantValue::query()->create([
        'company_id' => $company->getKey(),
        'product_family_id' => $family->getKey(),
        'variant_dimension_id' => $color->getKey(),
        'code' => 'RED',
        'label' => 'Duplicate Red',
    ]))->toThrow(QueryException::class);

    expect(fn () => DB::table('product_variant_value_assignments')->insert([
        'company_id' => $company->getKey(),
        'product_family_id' => $family->getKey(),
        'product_variant_relation_id' => $relation->getKey(),
        'variant_dimension_id' => $size->getKey(),
        'variant_value_id' => $red->getKey(),
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

function m25SchemaCompany(string $code): Company
{
    return Company::query()->create([
        'code' => $code,
        'name' => 'Company '.$code,
        'status' => 'active',
        'base_currency_code' => 'TRY',
        'timezone' => 'Europe/Istanbul',
    ]);
}

function m25SchemaProduct(Company $company, string $code): Product
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

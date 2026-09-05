<?php

use App\Modules\Products\Models\ProductFamily;
use App\Modules\Products\Models\ProductVariantRelation;
use App\Modules\Products\Models\VariantDimension;
use App\Modules\Products\Models\VariantValue;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\Products\M25ProductFamilyFixture;

uses(DatabaseMigrations::class);

it('keeps product identity independent from family lifecycle', function (): void {
    $company = M25ProductFamilyFixture::company('M25-ID');
    $product = M25ProductFamilyFixture::product($company, 'SKU-KEEP');
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
    $companyA = M25ProductFamilyFixture::company('M25-TA');
    $companyB = M25ProductFamilyFixture::company('M25-TB');
    $family = ProductFamily::query()->create([
        'company_id' => $companyA->getKey(),
        'code' => 'tenant-family',
        'name' => 'Tenant Family',
    ]);
    $foreignProduct = M25ProductFamilyFixture::product($companyB, 'SKU-FOREIGN');

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
    $company = M25ProductFamilyFixture::company('M25-INV');
    $product = M25ProductFamilyFixture::product($company, 'SKU-INV');
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

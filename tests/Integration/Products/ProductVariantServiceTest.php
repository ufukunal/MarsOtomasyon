<?php

use App\Modules\Products\Models\ProductVariantRelation;
use App\Modules\Products\Models\ProductVariantValueAssignment;
use App\Modules\Products\Variants\ProductVariantService;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\Products\M25ProductFamilyFixture;

uses(DatabaseMigrations::class);

it('creates families dimensions values and assigns a product deterministically', function (): void {
    $company = M25ProductFamilyFixture::company('M25-DOM');
    $product = M25ProductFamilyFixture::product($company, 'SKU-DOM');
    $service = app(ProductVariantService::class);
    $family = $service->createFamily((int) $company->getKey(), ' tshirt ', 'T Shirt');
    $color = $service->addDimension((int) $company->getKey(), (int) $family->getKey(), 'Color', 'Color', 0);
    $size = $service->addDimension((int) $company->getKey(), (int) $family->getKey(), 'Size', 'Size', 1);
    $red = $service->addValue((int) $company->getKey(), (int) $family->getKey(), (int) $color->getKey(), 'Red', 'Red');
    $large = $service->addValue((int) $company->getKey(), (int) $family->getKey(), (int) $size->getKey(), 'L', 'Large');

    $relation = $service->assignProduct((int) $company->getKey(), (int) $family->getKey(), (int) $product->getKey(), [
        (int) $size->getKey() => (int) $large->getKey(),
        (int) $color->getKey() => (int) $red->getKey(),
    ]);
    $expected = hash('sha256', $color->getKey().':'.$red->getKey().'|'.$size->getKey().':'.$large->getKey());

    expect($family->code)->toBe('TSHIRT')
        ->and($relation->variant_signature)->toBe($expected)
        ->and($relation->assignments)->toHaveCount(2)
        ->and($service->assignment((int) $company->getKey(), (int) $product->getKey())?->getKey())->toBe($relation->getKey());
});

it('allows only exact persisted-state replay', function (): void {
    $company = M25ProductFamilyFixture::company('M25-REPLAY');
    $product = M25ProductFamilyFixture::product($company, 'SKU-REPLAY');
    $service = app(ProductVariantService::class);
    $family = $service->createFamily((int) $company->getKey(), 'REPLAY', 'Replay');
    $dimension = $service->addDimension((int) $company->getKey(), (int) $family->getKey(), 'color', 'Color');
    $red = $service->addValue((int) $company->getKey(), (int) $family->getKey(), (int) $dimension->getKey(), 'red', 'Red');
    $selection = [(int) $dimension->getKey() => (int) $red->getKey()];

    $first = $service->assignProduct((int) $company->getKey(), (int) $family->getKey(), (int) $product->getKey(), $selection);
    $replay = $service->assignProduct((int) $company->getKey(), (int) $family->getKey(), (int) $product->getKey(), $selection);
    expect($replay->getKey())->toBe($first->getKey());

    ProductVariantValueAssignment::query()->where('product_variant_relation_id', $first->getKey())->delete();
    expect(fn () => $service->assignProduct((int) $company->getKey(), (int) $family->getKey(), (int) $product->getKey(), $selection))
        ->toThrow(DomainException::class, 'Persisted variant assignment drift detected.');
});

it('fails closed for duplicate combinations reassignment wrong dimension and cross-company inputs', function (): void {
    $company = M25ProductFamilyFixture::company('M25-GUARD');
    $otherCompany = M25ProductFamilyFixture::company('M25-GUARD2');
    $firstProduct = M25ProductFamilyFixture::product($company, 'SKU-G1');
    $secondProduct = M25ProductFamilyFixture::product($company, 'SKU-G2');
    $foreignProduct = M25ProductFamilyFixture::product($otherCompany, 'SKU-G3');
    $service = app(ProductVariantService::class);
    $family = $service->createFamily((int) $company->getKey(), 'GUARD', 'Guard');
    $otherFamily = $service->createFamily((int) $company->getKey(), 'GUARD2', 'Guard 2');
    $dimension = $service->addDimension((int) $company->getKey(), (int) $family->getKey(), 'color', 'Color');
    $red = $service->addValue((int) $company->getKey(), (int) $family->getKey(), (int) $dimension->getKey(), 'red', 'Red');
    $otherDimension = $service->addDimension((int) $company->getKey(), (int) $otherFamily->getKey(), 'size', 'Size');
    $large = $service->addValue((int) $company->getKey(), (int) $otherFamily->getKey(), (int) $otherDimension->getKey(), 'l', 'Large');
    $selection = [(int) $dimension->getKey() => (int) $red->getKey()];

    $service->assignProduct((int) $company->getKey(), (int) $family->getKey(), (int) $firstProduct->getKey(), $selection);

    expect(fn () => $service->assignProduct((int) $company->getKey(), (int) $family->getKey(), (int) $secondProduct->getKey(), $selection))
        ->toThrow(DomainException::class, 'already assigned');
    expect(fn () => $service->assignProduct((int) $company->getKey(), (int) $otherFamily->getKey(), (int) $firstProduct->getKey(), [(int) $otherDimension->getKey() => (int) $large->getKey()]))
        ->toThrow(DomainException::class, 'another product family');
    expect(fn () => $service->assignProduct((int) $company->getKey(), (int) $family->getKey(), (int) $secondProduct->getKey(), [(int) $dimension->getKey() => (int) $large->getKey()]))
        ->toThrow(DomainException::class, 'does not belong');
    expect(fn () => $service->assignProduct((int) $company->getKey(), (int) $family->getKey(), (int) $foreignProduct->getKey(), $selection))
        ->toThrow(DomainException::class, 'not found for company');
});

it('uses a PostgreSQL unique backstop for a concurrent combination race loser', function (): void {
    $company = M25ProductFamilyFixture::company('M25-RACE');
    $firstProduct = M25ProductFamilyFixture::product($company, 'SKU-RACE-1');
    $secondProduct = M25ProductFamilyFixture::product($company, 'SKU-RACE-2');
    $service = app(ProductVariantService::class);
    $family = $service->createFamily((int) $company->getKey(), 'RACE', 'Race');
    $dimension = $service->addDimension((int) $company->getKey(), (int) $family->getKey(), 'color', 'Color');
    $red = $service->addValue((int) $company->getKey(), (int) $family->getKey(), (int) $dimension->getKey(), 'red', 'Red');
    $relation = $service->assignProduct(
        (int) $company->getKey(),
        (int) $family->getKey(),
        (int) $firstProduct->getKey(),
        [(int) $dimension->getKey() => (int) $red->getKey()],
    );

    expect(fn () => ProductVariantRelation::query()->create([
        'company_id' => $company->getKey(),
        'product_family_id' => $family->getKey(),
        'product_id' => $secondProduct->getKey(),
        'variant_signature' => $relation->variant_signature,
    ]))->toThrow(QueryException::class);
});

it('preserves canonical products when a family is updated and deleted', function (): void {
    $company = M25ProductFamilyFixture::company('M25-LIFE');
    $product = M25ProductFamilyFixture::product($company, 'SKU-LIFE');
    $productId = (int) $product->getKey();
    $service = app(ProductVariantService::class);
    $family = $service->createFamily((int) $company->getKey(), 'LIFE', 'Life');
    $dimension = $service->addDimension((int) $company->getKey(), (int) $family->getKey(), 'color', 'Color');
    $red = $service->addValue((int) $company->getKey(), (int) $family->getKey(), (int) $dimension->getKey(), 'red', 'Red');
    $service->assignProduct((int) $company->getKey(), (int) $family->getKey(), $productId, [(int) $dimension->getKey() => (int) $red->getKey()]);

    $updated = $service->updateFamily((int) $company->getKey(), (int) $family->getKey(), 'LIFE-2', 'Life Two', ['description' => 'shared']);
    expect($updated->code)->toBe('LIFE-2')->and($product->fresh()?->code)->toBe('SKU-LIFE');

    $service->deleteFamily((int) $company->getKey(), (int) $family->getKey());
    expect(DB::table('products')->where('id', $productId)->exists())->toBeTrue()
        ->and(DB::table('product_variant_relations')->where('product_id', $productId)->exists())->toBeFalse();
});

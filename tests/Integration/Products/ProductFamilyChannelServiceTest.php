<?php

use App\Modules\Operations\ChannelService;
use App\Modules\Products\Variants\ProductFamilyChannelService;
use App\Modules\Products\Variants\ProductVariantService;
use DomainException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Fixtures\Products\M25ProductFamilyFixture;

uses(DatabaseMigrations::class);

it('maps one marketplace parent idempotently while preserving product child mappings', function (): void {
    $company = M25ProductFamilyFixture::company('M25-MAP');
    $productA = M25ProductFamilyFixture::product($company, 'SKU-MAP-A');
    $productB = M25ProductFamilyFixture::product($company, 'SKU-MAP-B');
    $variants = app(ProductVariantService::class);
    $family = $variants->createFamily((int) $company->getKey(), 'MAP', 'Mapped Family');
    $dimension = $variants->addDimension((int) $company->getKey(), (int) $family->getKey(), 'color', 'Color');
    $red = $variants->addValue((int) $company->getKey(), (int) $family->getKey(), (int) $dimension->getKey(), 'red', 'Red');
    $blue = $variants->addValue((int) $company->getKey(), (int) $family->getKey(), (int) $dimension->getKey(), 'blue', 'Blue');
    $variants->assignProduct((int) $company->getKey(), (int) $family->getKey(), (int) $productA->getKey(), [(int) $dimension->getKey() => (int) $red->getKey()]);
    $variants->assignProduct((int) $company->getKey(), (int) $family->getKey(), (int) $productB->getKey(), [(int) $dimension->getKey() => (int) $blue->getKey()]);

    $connectionId = app(ChannelService::class)->createConnection((int) $company->getKey(), 'woocommerce', 'M25 Woo', null, [], 'm25-secret');
    foreach ([$productA, $productB] as $index => $product) {
        DB::table('channel_product_mappings')->insert([
            'public_id' => (string) Str::ulid(),
            'company_id' => $company->getKey(),
            'connection_id' => $connectionId,
            'product_id' => $product->getKey(),
            'external_product_id' => 'PARENT-42',
            'external_variant_id' => 'CHILD-'.($index + 1),
            'external_sku' => $product->code,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    $service = app(ProductFamilyChannelService::class);
    $first = $service->mapParent((int) $company->getKey(), $connectionId, (int) $family->getKey(), 'woocommerce', 'PARENT-42', metadata: ['source' => 'm25']);
    $replay = $service->mapParent((int) $company->getKey(), $connectionId, (int) $family->getKey(), 'woocommerce', 'PARENT-42', metadata: ['source' => 'm25']);

    expect($replay->getKey())->toBe($first->getKey())
        ->and(DB::table('channel_product_mappings')->where('company_id', $company->getKey())->count())->toBe(2)
        ->and(DB::table('channel_product_mappings')->where('product_id', $productA->getKey())->value('external_variant_id'))->toBe('CHILD-1')
        ->and(DB::table('channel_product_mappings')->where('product_id', $productB->getKey())->value('external_variant_id'))->toBe('CHILD-2');
});

it('fails closed for parent identity drift collisions provider mismatch and tenant attacks', function (): void {
    $company = M25ProductFamilyFixture::company('M25-MAP-G');
    $foreign = M25ProductFamilyFixture::company('M25-MAP-F');
    $variants = app(ProductVariantService::class);
    $familyA = $variants->createFamily((int) $company->getKey(), 'MAP-A', 'Map A');
    $familyB = $variants->createFamily((int) $company->getKey(), 'MAP-B', 'Map B');
    $foreignFamily = $variants->createFamily((int) $foreign->getKey(), 'MAP-F', 'Map Foreign');
    $connectionId = app(ChannelService::class)->createConnection((int) $company->getKey(), 'woocommerce', 'M25 Woo Guard', null, [], 'm25-secret');
    $service = app(ProductFamilyChannelService::class);
    $service->mapParent((int) $company->getKey(), $connectionId, (int) $familyA->getKey(), 'woocommerce', 'PARENT-A');

    expect(fn () => $service->mapParent((int) $company->getKey(), $connectionId, (int) $familyA->getKey(), 'woocommerce', 'PARENT-DRIFT'))
        ->toThrow(DomainException::class, 'drift detected');
    expect(fn () => $service->mapParent((int) $company->getKey(), $connectionId, (int) $familyB->getKey(), 'woocommerce', 'PARENT-A'))
        ->toThrow(DomainException::class, 'already mapped');
    expect(fn () => $service->mapParent((int) $company->getKey(), $connectionId, (int) $familyB->getKey(), 'trendyol', 'PARENT-B'))
        ->toThrow(DomainException::class, 'does not match');
    expect(fn () => $service->mapParent((int) $foreign->getKey(), $connectionId, (int) $foreignFamily->getKey(), 'woocommerce', 'PARENT-F'))
        ->toThrow(DomainException::class, 'not found for company');
});

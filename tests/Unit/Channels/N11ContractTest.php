<?php

use App\Models\Period\ChannelProductListing;
use App\Models\Period\Product;
use App\Support\Channels\N11\N11PayloadBuilder;

function marsN11Listing(): ChannelProductListing
{
    $listing = new ChannelProductListing([
        'external_sku' => 'N11-SKU',
        'category_metadata' => ['list_price' => '15.00'],
    ]);
    $listing->setRelation('product', new Product(['code' => 'N11-PRODUCT']));

    return $listing;
}

it('caps stock and normalizes list price when the sale price is higher', function (): void {
    $builder = (new ReflectionClass(N11PayloadBuilder::class))->newInstanceWithoutConstructor();
    $stock = $builder->stockPriceSku(marsN11Listing(), '9999999', '20.00');
    expect($stock['stockCode'])->toBe('N11-SKU')
        ->and($stock['quantity'])->toBe(999999)
        ->and($stock['listPrice'])->toBe(20.0)
        ->and($stock['salePrice'])->toBe(20.0)
        ->and($stock['currencyType'])->toBe('TL');
});

it('does not add pricing fields when updating only inventory', function (): void {
    $builder = (new ReflectionClass(N11PayloadBuilder::class))->newInstanceWithoutConstructor();
    $stock = $builder->stockPriceSku(marsN11Listing(), '3', null);
    expect($stock)->toHaveKey('quantity')->not->toHaveKey('salePrice');
});

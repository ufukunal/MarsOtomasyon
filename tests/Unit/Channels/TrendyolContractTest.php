<?php

use App\Models\Period\ChannelProductListing;
use App\Models\Period\Product;
use App\Support\Channels\Trendyol\TrendyolPayloadBuilder;

function marsTrendyolListing(string $barcode = '123456'): ChannelProductListing
{
    $listing = new ChannelProductListing([
        'category_metadata' => ['list_price' => '5.00'],
    ]);
    $listing->setRelation('product', new Product(['code' => 'TY-SKU', 'barcode' => $barcode]));
    return $listing;
}

it('enforces Trendyol stock ceiling and raises list price to sale price', function (): void {
    $builder = (new ReflectionClass(TrendyolPayloadBuilder::class))->newInstanceWithoutConstructor();
    $item = $builder->inventoryItem(marsTrendyolListing(), '90000', '10.00');
    expect($item)->toMatchArray([
        'barcode' => '123456',
        'quantity' => 20000,
        'listPrice' => 10.0,
        'salePrice' => 10.0,
    ]);
});

it('rejects empty barcodes and invalid delivery lead times before publishing', function (): void {
    $builder = (new ReflectionClass(TrendyolPayloadBuilder::class))->newInstanceWithoutConstructor();
    expect(fn () => $builder->inventoryItem(marsTrendyolListing(''), '2', '1'))->toThrow(DomainException::class);
    expect(fn () => $builder->deliveryItem(marsTrendyolListing(), -1))->toThrow(DomainException::class);
    expect($builder->deliveryItem(marsTrendyolListing(), 3)['deliveryOptions']['deliveryDuration'])->toBe(3);
});

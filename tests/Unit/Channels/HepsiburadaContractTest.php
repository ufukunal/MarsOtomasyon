<?php

use App\Models\Period\ChannelProductListing;
use App\Models\Period\Product;
use App\Support\Channels\Hepsiburada\HepsiburadaPayloadBuilder;

function marsHepsiburadaListing(?string $hbSku = 'HB123'): ChannelProductListing
{
    $listing = new ChannelProductListing([
        'external_product_id' => $hbSku,
        'external_sku' => 'MY-SKU',
        'max_channel_quantity' => '500',
    ]);
    $listing->setRelation('product', new Product(['code' => 'SKU-FALLBACK']));

    return $listing;
}

it('formats merchant inventory and shipping payload without sending HTTP requests', function (): void {
    $builder = (new ReflectionClass(HepsiburadaPayloadBuilder::class))->newInstanceWithoutConstructor();
    $listing = marsHepsiburadaListing();
    expect($builder->stockItem($listing, '6.9'))->toBe([
        'hepsiburadaSku' => 'HB123',
        'merchantSku' => 'MY-SKU',
        'availableStock' => 6,
        'maximumPurchasableQuantity' => 500,
    ]);
    expect($builder->shippingItem($listing, -5)['dispatchTime'])->toBe(0);
    expect($builder->priceItem($listing, '12.345')['price'])->toBe(12.34);
});

it('rejects updates without HB product identifiers before any API calls', function (): void {
    $builder = (new ReflectionClass(HepsiburadaPayloadBuilder::class))->newInstanceWithoutConstructor();
    expect(fn () => $builder->stockItem(marsHepsiburadaListing(null), '3'))->toThrow(DomainException::class);
});

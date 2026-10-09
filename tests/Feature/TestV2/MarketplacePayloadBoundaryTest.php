<?php

use App\Models\Period\ChannelProductListing;
use App\Models\Period\Product;
use App\Support\Channels\Hepsiburada\HepsiburadaPayloadBuilder;
use App\Support\Channels\N11\N11PayloadBuilder;
use App\Support\Channels\Trendyol\TrendyolPayloadBuilder;
use App\Support\Channels\WooCommerce\WooCommercePayloadBuilder;

function v2PayloadListing(array $attributes = []): ChannelProductListing
{
    $listing = new ChannelProductListing(array_merge([
        'external_product_id' => 'HB-321',
        'external_sku' => '  SKU-101 ',
        'max_channel_quantity' => '12',
        'category_metadata' => ['list_price' => '8.00'],
    ], $attributes));
    $listing->setRelation('product', new Product([
        'code' => 'ITEM-101',
        'barcode' => 'BAR-101',
        'vat_rate' => '20',
    ]));

    return $listing;
}

it('v2 Trendyol inventory payload clamps quantity and repairs invalid lower list price', function () {
    $item = app(TrendyolPayloadBuilder::class)->inventoryItem(
        v2PayloadListing(), '21005.990', '12.34',
    );

    expect($item)->toBe([
        'barcode' => 'BAR-101',
        'quantity' => 20000,
        'salePrice' => 12.34,
        'listPrice' => 12.34,
    ]);
});

it('v2 Trendyol inventory sync refuses a missing barcode without touching remote APIs', function () {
    $listing = v2PayloadListing();
    $listing->setRelation('product', new Product(['code' => 'ITEM-101', 'barcode' => '']));

    expect(fn () => app(TrendyolPayloadBuilder::class)->inventoryItem($listing, '1', '10'))
        ->toThrow(DomainException::class, 'barcode zorunludur');
});

it('v2 Hepsiburada stock and price payloads bind both product identifiers', function () {
    $builder = app(HepsiburadaPayloadBuilder::class);
    $listing = v2PayloadListing(['external_sku' => ' sku 101 ']);

    expect($builder->stockItem($listing, '6.999'))->toBe([
        'hepsiburadaSku' => 'HB-321',
        'merchantSku' => 'SKU101',
        'availableStock' => 6,
        'maximumPurchasableQuantity' => 12,
    ]);
    expect($builder->priceItem($listing, '14.569'))->toBe([
        'hepsiburadaSku' => 'HB-321',
        'merchantSku' => 'SKU101',
        'price' => 14.56,
    ]);
});

it('v2 Hepsiburada stock payload refuses absent external product identity', function () {
    $listing = v2PayloadListing(['external_product_id' => null]);

    expect(fn () => app(HepsiburadaPayloadBuilder::class)->stockItem($listing, '5.000'))
        ->toThrow(DomainException::class, 'HB SKU zorunludur');
});

it('v2 N11 stock and price payloads preserve exact channel identity and enforce price floor', function () {
    $item = app(N11PayloadBuilder::class)->stockPriceSku(v2PayloadListing(), '12.789', '16.50');

    expect($item)->toBe([
        'stockCode' => 'SKU-101',
        'quantity' => 12,
        'listPrice' => 16.5,
        'salePrice' => 16.5,
        'currencyType' => 'TL',
    ]);
});

it('v2 N11 stock payload clamps negative and out-of-range channel quantity', function () {
    $builder = app(N11PayloadBuilder::class);
    expect($builder->stockPriceSku(v2PayloadListing(), '-2.000', null)['quantity'])->toBe(0)
        ->and($builder->stockPriceSku(v2PayloadListing(), '2000000.000', null)['quantity'])->toBe(999999);
});

it('v2 WooCommerce stock payload floors positive fractional units, clamps max and disables backorders', function () {
    $builder = app(WooCommercePayloadBuilder::class);

    expect($builder->stock('2.900'))->toBe([
        'manage_stock' => true,
        'stock_quantity' => 2,
        'stock_status' => 'instock',
        'backorders' => 'no',
    ]);
    expect($builder->stock('-1.000')['stock_status'])->toBe('outofstock')
        ->and($builder->stock('1000000000.000')['stock_quantity'])->toBe(999999999);
});

it('v2 WooCommerce price payload preserves two-decimal amount and refuses negatives', function () {
    $builder = app(WooCommercePayloadBuilder::class);
    expect($builder->price('10.129'))->toBe(['regular_price' => '10.12', 'sale_price' => '']);
    expect(fn () => $builder->price('-0.0001'))
        ->toThrow(DomainException::class, 'fiyat negatif olamaz');
});

<?php

use App\Models\Period\ChannelListingLocation;
use App\Models\Period\ChannelProductListing;
use App\Models\Period\Location;
use App\Models\Period\StockBalance;
use App\Support\Channels\ChannelStockResolver;
use DomainException;

it('selected location stock aggregation applies reservations withhold and max quantity', function () {
    [$company, $period] = $this->createCompanyWithPeriod('F9STOCK');
    $product = $this->createTestProduct(['code' => 'F9-STOCK']);

    $first = Location::query()->create([
        'code' => 'F9-WH-A',
        'name' => 'F9 Warehouse A',
        'kind' => 'warehouse',
        'is_default' => false,
        'is_active' => true,
    ]);
    $second = Location::query()->create([
        'code' => 'F9-WH-B',
        'name' => 'F9 Warehouse B',
        'kind' => 'warehouse',
        'is_default' => false,
        'is_active' => true,
    ]);

    StockBalance::query()->create([
        'product_id' => $product->id,
        'location_id' => $first->id,
        'quantity' => '10.000',
        'reserved' => '1.000',
        'consignment_reserved' => '0.000',
        'quarantine' => '1.000',
    ]);
    StockBalance::query()->create([
        'product_id' => $product->id,
        'location_id' => $second->id,
        'quantity' => '7.000',
        'reserved' => '0.000',
        'consignment_reserved' => '1.000',
        'quarantine' => '0.000',
    ]);

    $listing = ChannelProductListing::query()->create([
        'channel_account_id' => 9001,
        'product_id' => $product->id,
        'stock_mode' => 'stock',
        'withhold_quantity' => '2.000',
        'max_channel_quantity' => '10.000',
        'is_active' => true,
    ]);

    foreach ([$first, $second] as $location) {
        ChannelListingLocation::query()->create([
            'channel_product_listing_id' => $listing->id,
            'location_id' => $location->id,
        ]);
    }

    expect(app(ChannelStockResolver::class)->quantity($listing->fresh()))
        ->toBe('10.000');
});

it('stock mode rejects inactive mapped locations instead of leaking stock outside valid sales scope', function () {
    [$company, $period] = $this->createCompanyWithPeriod('F9SCOPE');
    $product = $this->createTestProduct(['code' => 'F9-SCOPE']);

    $location = Location::query()->create([
        'code' => 'F9-INACTIVE',
        'name' => 'F9 Inactive Warehouse',
        'kind' => 'warehouse',
        'is_default' => false,
        'is_active' => false,
    ]);

    StockBalance::query()->create([
        'product_id' => $product->id,
        'location_id' => $location->id,
        'quantity' => '50.000',
        'reserved' => '0.000',
        'consignment_reserved' => '0.000',
        'quarantine' => '0.000',
    ]);

    $listing = ChannelProductListing::query()->create([
        'channel_account_id' => 9002,
        'product_id' => $product->id,
        'stock_mode' => 'stock',
        'withhold_quantity' => '0.000',
        'is_active' => true,
    ]);

    ChannelListingLocation::query()->create([
        'channel_product_listing_id' => $listing->id,
        'location_id' => $location->id,
    ]);

    expect(fn () => app(ChannelStockResolver::class)->quantity($listing->fresh()))
        ->toThrow(
            DomainException::class,
            'Stock listing aktif satış lokasyonu dışında veya fason lokasyonda hesaplanamaz.',
        );
});

it('manual and production stock modes return configured deterministic quantities', function () {
    [$company, $period] = $this->createCompanyWithPeriod('F9MODES');

    $manualProduct = $this->createTestProduct(['code' => 'F9-MANUAL']);
    $manual = ChannelProductListing::query()->create([
        'channel_account_id' => 9003,
        'product_id' => $manualProduct->id,
        'stock_mode' => 'manual',
        'manual_quantity' => '13.250',
        'withhold_quantity' => '0.000',
        'is_active' => true,
    ]);

    $productionProduct = $this->createTestProduct(['code' => 'F9-PROD']);
    $production = ChannelProductListing::query()->create([
        'channel_account_id' => 9004,
        'product_id' => $productionProduct->id,
        'stock_mode' => 'production',
        'fixed_quantity' => '21.000',
        'lead_time_days' => 4,
        'withhold_quantity' => '0.000',
        'is_active' => true,
    ]);

    $resolver = app(ChannelStockResolver::class);

    expect($resolver->quantity($manual))->toBe('13.250')
        ->and($resolver->quantity($production))->toBe('21.000');
});

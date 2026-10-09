<?php

use App\Models\Period\ChannelListingLocation;
use App\Models\Period\ChannelProductListing;
use App\Models\Period\Contact;
use App\Models\Period\Location;
use App\Support\Channels\ChannelStockResolver;

it('v2 channel manual and production modes fail closed on missing configured quantities', function () {
    $this->createCompanyWithPeriod('V2CHMOD');

    foreach (['manual', 'production'] as $mode) {
        $product = $this->createTestProduct(['code' => 'V2-'.strtoupper($mode)]);
        $listing = ChannelProductListing::query()->create([
            'channel_account_id' => 9201,
            'product_id' => $product->id,
            'stock_mode' => $mode,
            'withhold_quantity' => '0.000',
            'is_active' => true,
        ]);

        expect(fn () => app(ChannelStockResolver::class)->quantity($listing))
            ->toThrow(DomainException::class);
    }
});

it('v2 channel stock mode refuses empty warehouse scope instead of exposing all warehouses', function () {
    $this->createCompanyWithPeriod('V2CHNONE');
    $product = $this->createTestProduct(['code' => 'V2-NOSCOPE']);

    $listing = ChannelProductListing::query()->create([
        'channel_account_id' => 9202,
        'product_id' => $product->id,
        'stock_mode' => 'stock',
        'withhold_quantity' => '0.000',
        'is_active' => true,
    ]);

    expect(fn () => app(ChannelStockResolver::class)->quantity($listing))
        ->toThrow(DomainException::class, 'location kapsamı boş');
});

it('v2 channel stock mode excludes subcontractor warehouses even with available inventory', function () {
    $this->createCompanyWithPeriod('V2CHSUB');
    $product = $this->createTestProduct(['code' => 'V2-SUBCONTRACT']);
    $subcontractor = Contact::query()->create(['title' => 'V2 Subcontractor', 'type' => 'legal']);
    $location = Location::query()->create([
        'code' => 'V2-SUBLOC',
        'name' => 'External Subcontractor',
        'kind' => 'subcontractor',
        'subcontractor_contact_id' => $subcontractor->id,
        'is_active' => true,
        'is_default' => false,
    ]);
    $listing = ChannelProductListing::query()->create([
        'channel_account_id' => 9203,
        'product_id' => $product->id,
        'stock_mode' => 'stock',
        'withhold_quantity' => '0.000',
        'is_active' => true,
    ]);
    ChannelListingLocation::query()->create([
        'channel_product_listing_id' => $listing->id,
        'location_id' => $location->id,
    ]);

    expect(fn () => app(ChannelStockResolver::class)->quantity($listing))
        ->toThrow(DomainException::class, 'fason lokasyonda hesaplanamaz');
});

<?php

namespace App\Support\Channels;

use App\Models\Period\ChannelAccountPeriodSetting;
use App\Models\Period\ChannelProductListing;
use App\Support\Pricing\PriceResolver;
use DomainException;

final class ChannelPriceResolver
{
    public function __construct(private readonly PriceResolver $prices) {}

    public function price(ChannelProductListing $listing): string
    {
        $listing->loadMissing('product');

        if ($listing->price_override !== null) {
            return bcadd((string) $listing->price_override, '0', 4);
        }

        if ((string) $listing->product->currency !== 'TRY') {
            throw new DomainException('Kanal fiyatı ilk sürümde yalnız TRY olabilir; price override girilmelidir.');
        }

        $contact = ChannelAccountPeriodSetting::query()
            ->with('marketplaceCustomerContact')
            ->where('channel_account_id', $listing->channel_account_id)
            ->first()?->marketplaceCustomerContact;

        return $this->prices->resolve($listing->product, $contact);
    }
}

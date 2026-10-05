<?php

namespace App\Contracts\Channels;

use App\DataObjects\Channels\ChannelInboundEvent;
use App\DataObjects\Channels\ChannelOperationResult;
use App\Models\Period\ChannelProductListing;
use App\Models\SalesChannelAccount;
use Carbon\CarbonImmutable;

interface ChannelAdapter
{
    public function testConnection(SalesChannelAccount $account): ChannelOperationResult;

    public function publishListing(
        SalesChannelAccount $account,
        ChannelProductListing $listing,
    ): ChannelOperationResult;

    public function updateContent(
        SalesChannelAccount $account,
        ChannelProductListing $listing,
    ): ChannelOperationResult;

    public function updatePrice(
        SalesChannelAccount $account,
        ChannelProductListing $listing,
        string $priceTry,
    ): ChannelOperationResult;

    public function updateStock(
        SalesChannelAccount $account,
        ChannelProductListing $listing,
        string $quantity,
        ?int $leadTimeDays = null,
    ): ChannelOperationResult;

    /** @return list<ChannelInboundEvent> */
    public function fetchOrders(
        SalesChannelAccount $account,
        ?CarbonImmutable $since = null,
    ): array;

    /** @return list<ChannelInboundEvent> */
    public function fetchCancellations(
        SalesChannelAccount $account,
        ?CarbonImmutable $since = null,
    ): array;

    /** @return list<ChannelInboundEvent> */
    public function fetchReturns(
        SalesChannelAccount $account,
        ?CarbonImmutable $since = null,
    ): array;

    /** @param array<string, scalar|null> $shipment */
    public function pushShipmentStatus(
        SalesChannelAccount $account,
        array $shipment,
    ): ChannelOperationResult;
}

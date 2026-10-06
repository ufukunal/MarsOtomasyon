<?php

namespace App\Actions\Channels;

use App\Models\Period\ChannelProductListing;
use App\Models\SalesChannelAccount;
use App\Support\Auth\MutationAuthorizer;
use App\Support\Channels\ChannelAdapterResolver;
use App\Support\Channels\ChannelPayloadHasher;
use App\Support\Channels\ChannelPriceResolver;
use App\Support\Channels\ChannelStockResolver;
use App\Support\Channels\ChannelSyncRecorder;
use App\Support\Period\PeriodContext;
use DomainException;
use Throwable;

final class SyncChannelListing
{
    public function __construct(
        private readonly ChannelAdapterResolver $adapters,
        private readonly ChannelPayloadHasher $hasher,
        private readonly ChannelPriceResolver $prices,
        private readonly ChannelStockResolver $stock,
        private readonly ChannelSyncRecorder $sync,
    ) {}

    public function publish(ChannelProductListing $listing): void
    {
        MutationAuthorizer::authorize('channel_listings.update');
        $this->perform($listing, 'publish', fn ($adapter, $account) => $adapter->publishListing($account, $listing));
    }

    public function content(ChannelProductListing $listing): void
    {
        MutationAuthorizer::authorize('channel_listings.update');
        $this->perform($listing, 'content', fn ($adapter, $account) => $adapter->updateContent($account, $listing));
    }

    public function stock(ChannelProductListing $listing): void
    {
        MutationAuthorizer::authorize('channel_listings.update');
        $quantity = $this->stock->quantity($listing);
        $this->perform(
            $listing,
            'stock',
            fn ($adapter, $account) => $adapter->updateStock(
                $account,
                $listing,
                $quantity,
                $listing->lead_time_days,
            ),
            ['quantity' => $quantity],
        );
    }

    public function price(ChannelProductListing $listing): void
    {
        MutationAuthorizer::authorize('channel_listings.update');
        $price = $this->prices->price($listing);
        $this->perform(
            $listing,
            'price',
            fn ($adapter, $account) => $adapter->updatePrice($account, $listing, $price),
            ['price' => $price],
        );
    }

    /**
     * @param  array<string,mixed>  $fingerprint
     */
    private function perform(
        ChannelProductListing $listing,
        string $action,
        callable $operation,
        array $fingerprint = [],
    ): void {
        PeriodContext::ensureWritable();
        $listing->loadMissing('product');

        if (! $listing->is_active) {
            throw new DomainException('Pasif kanal listing sync edilemez.');
        }

        $account = SalesChannelAccount::query()->findOrFail($listing->channel_account_id);

        if (! $account->is_active || (int) $account->company_id !== (int) PeriodContext::companyId()) {
            throw new DomainException('Kanal hesabı pasif veya aktif şirkete ait değil.');
        }

        $event = $this->sync->queue(
            channelAccountId: (int) $account->id,
            direction: 'outbound',
            entityType: 'listing',
            action: $action,
            entityId: (int) $listing->id,
            externalId: $listing->external_listing_id,
            payloadHash: $this->hasher->hash([
                'listing_id' => $listing->id,
                'listing_version' => $listing->version,
                'action' => $action,
                ...$fingerprint,
            ]),
        );
        $attempt = $this->sync->startAttempt($event);

        try {
            $result = $operation($this->adapters->resolve($account), $account);

            if (! $result->success) {
                throw new DomainException($result->message ?: 'Kanal işlemi başarısız.');
            }

            $this->sync->markSuccess($attempt, $result->externalId);
        } catch (Throwable $exception) {
            $this->sync->markFailure($attempt, $exception->getMessage());

            throw $exception;
        }
    }
}

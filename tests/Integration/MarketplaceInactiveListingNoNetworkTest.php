<?php

use App\Actions\Channels\SyncChannelListing;
use App\Models\Period\ChannelProductListing;
use App\Models\Period\Product;
use Illuminate\Support\Facades\Http;
use Tests\Support\AuthorizedPeriod;
use Tests\Support\IsolatedPostgres;

it('refuses publishing or refreshing the content of an inactive channel listing without a remote request', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();
        Http::preventStrayRequests();
        Http::fake();

        try {
            $listing = new ChannelProductListing(['is_active' => false]);
            $listing->setRelation('product', new Product(['code' => 'V4-NETWORK-GUARD']));
            $sync = app(SyncChannelListing::class);

            expect(fn () => $sync->publish($listing))->toThrow(DomainException::class);
            expect(fn () => $sync->content($listing))->toThrow(DomainException::class);
            Http::assertNothingSent();
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});

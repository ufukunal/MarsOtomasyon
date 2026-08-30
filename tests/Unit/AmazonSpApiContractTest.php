<?php

namespace Tests\Unit;

use App\Modules\Commerce\Providers\Amazon\AmazonSpApiClient;
use DomainException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class AmazonSpApiContractTest extends TestCase
{
    /** @return array<string,mixed> */
    private function credentials(): array
    {
        return [
            'seller_id' => 'A1SELLER',
            'marketplace_id' => 'A1MARKETPLACE',
            'region' => 'eu',
            'environment' => 'sandbox',
            'access_token' => 'Atza|sandbox-token',
            'user_agent' => 'MarsOtomasyon/1.0',
        ];
    }

    public function test_eu_sandbox_listing_orders_and_reports_use_current_contracts(): void
    {
        Http::fake(['*' => Http::response(['ok' => true], 200)]);
        $client = $this->app->make(AmazonSpApiClient::class);
        $credentials = $this->credentials();

        $client->connectionTest($credentials);
        $client->searchListings($credentials, ['pageSize' => 10]);
        $client->searchOrders($credentials, [
            'lastUpdatedAfter' => '2026-08-01T00:00:00Z',
            'includedData' => 'FULFILLMENT,PROCEEDS,PACKAGES',
        ]);
        $client->getOrder($credentials, 'ORDER-1', ['FULFILLMENT', 'PROCEEDS', 'PACKAGES']);
        $client->requestSettlementReport($credentials);
        $client->requestReturnsReport($credentials, '2026-08-01T00:00:00Z', '2026-08-30T00:00:00Z');

        Http::assertSent(fn (Request $request): bool => $request->hasHeader('x-amz-access-token', 'Atza|sandbox-token')
            && $request->hasHeader('User-Agent', 'MarsOtomasyon/1.0'));
        Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
            && str_starts_with($request->url(), 'https://sandbox.sellingpartnerapi-eu.amazon.com/listings/2021-08-01/items/A1SELLER?'));
        Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
            && str_starts_with($request->url(), 'https://sandbox.sellingpartnerapi-eu.amazon.com/orders/2026-01-01/orders?'));
        Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
            && str_starts_with($request->url(), 'https://sandbox.sellingpartnerapi-eu.amazon.com/orders/2026-01-01/orders/ORDER-1?'));
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === 'https://sandbox.sellingpartnerapi-eu.amazon.com/reports/2021-06-30/reports'
            && $request['reportType'] === 'GET_V2_SETTLEMENT_REPORT_DATA_FLAT_FILE_V2');
    }

    public function test_lwa_refresh_token_exchange_is_used_when_access_token_is_absent(): void
    {
        Http::fake([
            AmazonSpApiClient::LWA_TOKEN_URL => Http::response(['access_token' => 'Atza|fresh'], 200),
            '*' => Http::response([], 200),
        ]);
        $credentials = $this->credentials();
        unset($credentials['access_token']);
        $credentials['client_id'] = 'client-id';
        $credentials['client_secret'] = 'client-secret';
        $credentials['refresh_token'] = 'Atzr|refresh';

        $this->app->make(AmazonSpApiClient::class)->connectionTest($credentials);

        Http::assertSent(fn (Request $request): bool => $request->url() === AmazonSpApiClient::LWA_TOKEN_URL
            && $request['grant_type'] === 'refresh_token'
            && $request['refresh_token'] === 'Atzr|refresh');
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/listings/2021-08-01/items/A1SELLER')
            && $request->hasHeader('x-amz-access-token', 'Atza|fresh'));
    }

    public function test_desired_state_patch_uses_official_top_level_offer_attributes(): void
    {
        Http::fake(['*' => Http::response(['status' => 'ACCEPTED'], 200)]);
        $client = $this->app->make(AmazonSpApiClient::class);

        $client->patchDesiredState($this->credentials(), 'SKU-1', 7, '123.45', 'TRY');

        Http::assertSent(function (Request $request): bool {
            if ($request->method() !== 'PATCH' || ! str_contains($request->url(), '/listings/2021-08-01/items/A1SELLER/SKU-1?marketplaceIds=A1MARKETPLACE')) {
                return false;
            }
            $patches = $request['patches'];

            return is_array($patches)
                && ($patches[0]['path'] ?? null) === '/attributes/fulfillment_availability'
                && ($patches[0]['value'][0]['quantity'] ?? null) === 7
                && ($patches[1]['path'] ?? null) === '/attributes/purchasable_offer'
                && ($patches[1]['value'][0]['currency'] ?? null) === 'TRY';
        });
    }

    public function test_order_search_rejects_ambiguous_time_filters_before_transport(): void
    {
        Http::fake();
        $this->expectException(DomainException::class);

        $this->app->make(AmazonSpApiClient::class)->searchOrders($this->credentials(), [
            'createdAfter' => '2026-08-01T00:00:00Z',
            'lastUpdatedAfter' => '2026-08-01T00:00:00Z',
        ]);
    }
}

<?php

namespace Tests\Unit;

use App\Modules\Commerce\ProviderRegistry;
use App\Modules\Commerce\Providers\Hepsiburada\HepsiburadaClient;
use DomainException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class HepsiburadaContractTest extends TestCase
{
    /** @return array<string,mixed> */
    private function credentials(): array
    {
        return [
            'merchant_id' => 'merchant-123',
            'username' => 'api-user',
            'password' => 'api-password',
            'user_agent' => 'MarsOtomasyon',
            'environment' => 'sit',
        ];
    }

    public function test_registry_exposes_only_verified_read_contracts_without_publish_claims(): void
    {
        $registry = $this->app->make(ProviderRegistry::class);

        self::assertTrue($registry->isContractVerified('hepsiburada'));
        self::assertFalse($registry->isMarketplaceVerified('hepsiburada'));
        self::assertTrue($registry->supports('hepsiburada', 'listing_read_contract'));
        self::assertTrue($registry->supports('hepsiburada', 'order_polling_contract'));
        self::assertTrue($registry->supports('hepsiburada', 'webhook_basic_auth_contract'));
        self::assertFalse($registry->supports('hepsiburada', 'stock_publish'));
        self::assertFalse($registry->supports('hepsiburada', 'price_publish'));
        self::assertFalse($registry->supports('hepsiburada', 'connection_test'));
    }

    public function test_sit_listing_order_product_and_inventory_status_contracts_use_official_hosts_and_headers(): void
    {
        Http::fake(['*' => Http::response([], 200)]);
        $client = $this->app->make(HepsiburadaClient::class);
        $credentials = $this->credentials();

        $client->connectionTest($credentials);
        $client->listings($credentials, ['offset' => 10, 'limit' => 25, 'merchantSkuList' => 'SKU-1']);
        $client->paidOrders($credentials, ['offset' => 0, 'limit' => 100, 'begindate' => '2026-08-01']);
        $client->orderDetail($credentials, 'ORDER-1');
        $client->inventoryUploadStatus($credentials, 'upload-1');
        $client->productsByStatus($credentials, 'Approved', ['page' => 0, 'size' => 1000]);

        $expectedAuthorization = 'Basic '.base64_encode('api-user:api-password');
        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', $expectedAuthorization)
            && $request->hasHeader('User-Agent', 'MarsOtomasyon'));
        Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
            && $request->url() === HepsiburadaClient::SIT_LISTING_BASE_URL.'/listings/merchantid/merchant-123?offset=0&limit=1');
        Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
            && str_starts_with($request->url(), HepsiburadaClient::SIT_OMS_BASE_URL.'/orders/merchantid/merchant-123?'));
        Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
            && $request->url() === HepsiburadaClient::SIT_OMS_BASE_URL.'/orders/merchantid/merchant-123/ordernumber/ORDER-1');
        Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
            && $request->url() === HepsiburadaClient::SIT_LISTING_BASE_URL.'/listings/merchantid/merchant-123/inventory-uploads/id/upload-1');
        Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
            && str_starts_with($request->url(), HepsiburadaClient::SIT_MPOP_BASE_URL.'/product/api/products/products-by-merchant-and-status?'));
    }

    public function test_paid_orders_rejects_missing_or_oversized_limit_before_transport(): void
    {
        Http::fake();
        $client = $this->app->make(HepsiburadaClient::class);

        $this->expectException(DomainException::class);
        $client->paidOrders($this->credentials(), ['offset' => 0, 'limit' => 101]);
    }

    public function test_invalid_user_agent_is_rejected_before_transport(): void
    {
        Http::fake();
        $client = $this->app->make(HepsiburadaClient::class);
        $credentials = $this->credentials();
        $credentials['user_agent'] = "bad\r\nheader";

        $this->expectException(DomainException::class);
        $client->connectionTest($credentials);
    }
}

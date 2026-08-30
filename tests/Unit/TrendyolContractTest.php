<?php

namespace Tests\Unit;

use App\Modules\Commerce\ProviderRegistry;
use App\Modules\Commerce\Providers\Trendyol\TrendyolClient;
use DomainException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class TrendyolContractTest extends TestCase
{
    /** @return array<string,mixed> */
    private function credentials(): array
    {
        return [
            'seller_id' => 123456,
            'api_key' => 'api-key',
            'api_secret' => 'api-secret',
            'integration_name' => 'MarsOtomasyon',
            'environment' => 'stage',
            'storefront_code' => 'TR',
        ];
    }

    public function test_registry_marks_trendyol_contract_verified_without_claiming_production_verification(): void
    {
        $registry = $this->app->make(ProviderRegistry::class);

        self::assertTrue($registry->isContractVerified('trendyol'));
        self::assertFalse($registry->isMarketplaceVerified('trendyol'));
        self::assertTrue($registry->supports('trendyol', 'connection_test'));
        self::assertTrue($registry->supports('trendyol', 'product_publish'));
        self::assertTrue($registry->supports('trendyol', 'order_polling'));
        self::assertTrue($registry->supports('trendyol', 'settlement_evidence'));
    }

    public function test_stage_connection_and_product_v2_contract_use_fixed_official_paths_and_headers(): void
    {
        Http::fake(['*' => Http::response(['batchRequestId' => 'batch-1'], 200)]);
        $client = $this->app->make(TrendyolClient::class);
        $credentials = $this->credentials();

        $client->connectionTest($credentials);
        $client->categories($credentials);
        $client->categoryAttributes($credentials, 411);
        $client->categoryAttributeValues($credentials, 411, 14, ['page' => 0, 'size' => 1000]);
        $client->createProductsV2($credentials, ['items' => [['barcode' => 'ABC-1']]]);
        $client->batchResult($credentials, 'batch-1');
        $client->updatePriceAndInventory($credentials, ['items' => [['barcode' => 'ABC-1', 'quantity' => 5]]]);

        $expectedAuthorization = 'Basic '.base64_encode('api-key:api-secret');
        Http::assertSent(function (Request $request) use ($expectedAuthorization): bool {
            return $request->hasHeader('Authorization', $expectedAuthorization)
                && $request->hasHeader('User-Agent', '123456 - MarsOtomasyon')
                && $request->hasHeader('storeFrontCode', 'TR');
        });
        Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
            && $request->url() === TrendyolClient::STAGE_BASE_URL.'/integration/sellers/123456/addresses');
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === TrendyolClient::STAGE_BASE_URL.'/integration/product/sellers/123456/v2/products');
        Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
            && $request->url() === TrendyolClient::STAGE_BASE_URL.'/integration/product/sellers/123456/products/batch-requests/batch-1');
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === TrendyolClient::STAGE_BASE_URL.'/integration/inventory/sellers/123456/products/price-and-inventory');
    }

    public function test_order_return_qna_invoice_finance_and_webhook_contracts_are_typed(): void
    {
        Http::fake(['*' => Http::response([], 200)]);
        $client = $this->app->make(TrendyolClient::class);
        $credentials = $this->credentials();

        $client->ordersV2($credentials, ['status' => 'Created']);
        $client->cancelPackageItems($credentials, 987, ['lines' => [['lineId' => 1, 'quantity' => 1]], 'reasonId' => 500]);
        $client->claims($credentials, ['claimItemStatus' => 'Created']);
        $client->createClaim($credentials, ['claimItems' => [['orderNumber' => 'ORDER-1']]]);
        $client->questions($credentials, ['status' => 'WAITING_FOR_ANSWER']);
        $client->answerQuestion($credentials, 456, 'Bu ürün stoklarımızda mevcuttur.');
        $client->sendInvoiceLink($credentials, ['invoiceLink' => 'https://example.test/i.pdf', 'shipmentPackageId' => 987]);
        $client->settlements($credentials, ['transactionType' => 'Sale', 'startDate' => 1, 'endDate' => 2]);
        $client->otherFinancials($credentials, ['transactionType' => 'CashAdvance', 'startDate' => 1, 'endDate' => 2]);
        $client->registerWebhook($credentials, ['url' => 'https://example.test/hooks/trendyol', 'authenticationType' => 'API_KEY']);

        Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
            && str_starts_with($request->url(), TrendyolClient::STAGE_BASE_URL.'/integration/order/sellers/123456/v2/orders'));
        Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT'
            && $request->url() === TrendyolClient::STAGE_BASE_URL.'/integration/order/sellers/123456/shipment-packages/987/items/unsupplied');
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === TrendyolClient::STAGE_BASE_URL.'/integration/qna/sellers/123456/questions/456/answers');
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === TrendyolClient::STAGE_BASE_URL.'/integration/sellers/123456/seller-invoice-links');
        Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
            && str_starts_with($request->url(), TrendyolClient::STAGE_BASE_URL.'/integration/finance/che/sellers/123456/settlements'));
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === TrendyolClient::STAGE_BASE_URL.'/integration/webhook/sellers/123456/webhooks');
    }

    public function test_invalid_credentials_and_oversized_batches_fail_before_transport(): void
    {
        Http::fake();
        $client = $this->app->make(TrendyolClient::class);

        $this->expectException(DomainException::class);
        $client->connectionTest(['seller_id' => 0, 'api_key' => 'key', 'api_secret' => 'secret']);
    }

    public function test_product_write_rejects_more_than_one_thousand_items(): void
    {
        Http::fake();
        $client = $this->app->make(TrendyolClient::class);
        $items = array_fill(0, 1001, ['barcode' => 'ABC']);

        $this->expectException(DomainException::class);
        $client->createProductsV2($this->credentials(), ['items' => $items]);
    }
}

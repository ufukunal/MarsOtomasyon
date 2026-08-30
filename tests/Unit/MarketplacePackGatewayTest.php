<?php

use App\Modules\Commerce\MarketplacePack\MarketplacePackGateway;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('locks n11 REST auth stock-price task and order contracts', function (): void {
    Http::fake([
        'https://api.n11.com/ms/product-query*' => Http::response(['content' => []], 200),
        'https://api.n11.com/ms/product/tasks/price-stock-update' => Http::response(['id' => 9001, 'status' => 'IN_QUEUE'], 200),
        'https://api.n11.com/ms/product/task-details/page-query' => Http::response(['taskId' => 9001, 'status' => 'PROCESSED'], 200),
        'https://api.n11.com/rest/delivery/v1/shipmentPackages*' => Http::response(['content' => []], 200),
    ]);
    $gateway = app(MarketplacePackGateway::class);
    $credentials = ['app_key' => 'key', 'app_secret' => 'secret', 'integrator' => 'MarsOtomasyon'];

    expect($gateway->connectionTest('n11', $credentials)->successful())->toBeTrue();
    expect($gateway->publishDesiredState('n11', $credentials, 'SKU-1', ['quantity' => 3, 'price' => '125.50', 'currency_code' => 'TRY'])->json('id'))->toBe(9001);
    expect($gateway->taskStatus('n11', $credentials, '9001')->json('status'))->toBe('PROCESSED');
    expect($gateway->orders('n11', $credentials, ['page' => 1, 'size' => 50, 'start' => '2026-08-29T00:00:00+03:00', 'end' => '2026-08-30T00:00:00+03:00'])->successful())->toBeTrue();

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.n11.com/ms/product/tasks/price-stock-update'
        && ($request->header('appkey')[0] ?? null) === 'key'
        && ($request->header('appsecret')[0] ?? null) === 'secret'
        && ($request->data()['payload']['skus'][0]['currencyType'] ?? null) === 'TL');
});

it('locks PttAVM API-key token correlation and tracking contracts', function (): void {
    Http::fake([
        'https://integration-api.pttavm.com/api/v1/categories/main' => Http::response(['success' => true], 200),
        'https://integration-api.pttavm.com/api/v1/products/stock-prices' => Http::response(['trackingId' => 'PTT-1'], 200),
        'https://integration-api.pttavm.com/api/v1/products/tracking-result/PTT-1' => Http::response(['trackingId' => 'PTT-1', 'status' => 'done'], 200),
        'https://integration-api.pttavm.com/api/v1/orders/search*' => Http::response([], 200),
    ]);
    $gateway = app(MarketplacePackGateway::class);
    $credentials = ['api_key' => 'ptt-key', 'access_token' => 'ptt-token'];

    expect($gateway->connectionTest('pttavm', $credentials)->successful())->toBeTrue();
    expect($gateway->publishDesiredState('pttavm', $credentials, '8690000000001', ['quantity' => 8, 'price' => '99.90', 'currency_code' => 'TRY'])->json('trackingId'))->toBe('PTT-1');
    expect($gateway->taskStatus('pttavm', $credentials, 'PTT-1')->successful())->toBeTrue();
    expect($gateway->orders('pttavm', $credentials, ['start' => '2026-08-29T00:00:00+03:00', 'end' => '2026-08-30T00:00:00+03:00'])->successful())->toBeTrue();

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'pttavm.com')
        && ($request->header('Api-Key')[0] ?? null) === 'ptt-key'
        && ($request->header('Access-Token')[0] ?? null) === 'ptt-token'
        && (string) ($request->header('X-Correlation-Id')[0] ?? '') !== '');
});

it('locks idefix X-API-KEY batch inventory and shipment contracts', function (): void {
    Http::fake([
        'https://merchantapi.idefix.com/pim/product-category' => Http::response([], 200),
        'https://merchantapi.idefix.com/pim/catalog/vendor-1/inventory-upload' => Http::response(['batchRequestId' => 'IDF-1'], 200),
        'https://merchantapi.idefix.com/pim/batch-result/IDF-1' => Http::response(['batchRequestId' => 'IDF-1', 'status' => 'DONE'], 200),
        'https://merchantapi.idefix.com/oms/vendor-1/list*' => Http::response(['items' => []], 200),
    ]);
    $gateway = app(MarketplacePackGateway::class);
    $credentials = ['api_key' => 'idf-key', 'api_secret' => 'idf-secret', 'vendor_id' => 'vendor-1'];

    expect($gateway->connectionTest('idefix', $credentials)->successful())->toBeTrue();
    expect($gateway->publishDesiredState('idefix', $credentials, '8680000000002', ['quantity' => 4, 'price' => '150', 'currency_code' => 'TRY'])->json('batchRequestId'))->toBe('IDF-1');
    expect($gateway->taskStatus('idefix', $credentials, 'IDF-1')->successful())->toBeTrue();
    expect($gateway->orders('idefix', $credentials, ['page' => 1, 'size' => 50])->successful())->toBeTrue();

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'merchantapi.idefix.com')
        && ($request->header('X-API-KEY')[0] ?? null) === base64_encode('idf-key:idf-secret'));
});

it('locks Allesgo sandbox token order and kuruş price contracts', function (): void {
    Http::fake([
        'https://sandbox-api.allesgo.com/v1.0/order/store/store-1*' => Http::response(['data' => []], 200),
        'https://sandbox-api.allesgo.com/v1.0/product/update/store/store-1*' => Http::response(['id' => 'ALG-1'], 200),
    ]);
    $gateway = app(MarketplacePackGateway::class);
    $credentials = ['store_id' => 'store-1', 'access_token' => 'alg-token', 'environment' => 'sandbox'];

    expect($gateway->connectionTest('allesgo', $credentials)->successful())->toBeTrue();
    expect($gateway->publishDesiredState('allesgo', $credentials, 'product-1', ['quantity' => 9, 'price' => '10.25', 'currency_code' => 'TRY'])->json('id'))->toBe('ALG-1');
    expect($gateway->orders('allesgo', $credentials, ['start' => '2026-08-29T00:00:00+03:00', 'end' => '2026-08-30T00:00:00+03:00'])->successful())->toBeTrue();

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/product/update/store/store-1')
        && str_contains($request->url(), 'access_token=alg-token')
        && ($request->data()['price'] ?? null) === 1025
        && ($request->data()['stock_count'] ?? null) === 9);
});

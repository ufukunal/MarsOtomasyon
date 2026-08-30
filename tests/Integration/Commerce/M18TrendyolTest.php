<?php

use App\Modules\Accounts\Enums\AccountStatus;
use App\Modules\Accounts\Enums\AccountType;
use App\Modules\Accounts\Enums\TaxIdentityType;
use App\Modules\Accounts\Models\Account;
use App\Modules\Commerce\ChannelCenterService;
use App\Modules\Commerce\ProviderRegistry;
use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Tax;
use App\Modules\Operations\ChannelService;
use App\Modules\Products\Enums\ProductStatus;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\Unit;
use DomainException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(DatabaseMigrations::class);

it('runs Trendyol V2 connection stock price cooldown polling and API key webhook seams', function (): void {
    Queue::fake();
    $priceCalls = 0;
    $pricePayload = null;
    $orderPayload = m18TrendyolOrder('TY-9001', 3330111111, 1788100000000, 'Created');

    Http::fake(function (Request $request) use (&$priceCalls, &$pricePayload, $orderPayload) {
        $url = $request->url();
        if (str_ends_with($url, '/integration/sellers/99999/addresses')) {
            return Http::response(['addresses' => []], 200);
        }
        if (str_contains($url, '/integration/inventory/sellers/99999/products/price-and-inventory')) {
            $priceCalls++;
            $pricePayload = $request->data();

            return Http::response(['batchRequestId' => 'ty-batch-1'], 200);
        }
        if (str_contains($url, '/integration/order/sellers/99999/v2/orders')) {
            return Http::response([
                'totalElements' => 1,
                'totalPages' => 1,
                'page' => 0,
                'size' => 50,
                'content' => [$orderPayload],
            ], 200);
        }

        return Http::response(['unexpected' => $url], 500);
    });

    [$company, $customer, $product] = m18TrendyolFixture();
    app(ActiveCompanyContext::class)->set($company);
    $commerce = app(ChannelCenterService::class);
    $channels = app(ChannelService::class);
    $registry = app(ProviderRegistry::class);

    $connectionPublicId = $commerce->createConnection(
        companyId: (int) $company->getKey(),
        provider: 'trendyol',
        name: 'Trendyol Stage',
        baseUrl: null,
        credentials: [
            'seller_id' => '99999',
            'api_key' => 'ty-api-key',
            'api_secret' => 'ty-api-secret',
            'integration_name' => 'MarsOtomasyon',
            'storefront_code' => 'TR',
            'environment' => 'stage',
            'webhook_authentication_type' => 'API_KEY',
            'default_account_id' => (int) $customer->getKey(),
            'price_basis' => 'gross',
            'order_series' => 'trendyol',
        ],
        webhookSecret: 'ty-callback-api-key',
        financialMode: 'direct_account',
        defaultAccountId: (int) $customer->getKey(),
    );

    expect($registry->get('trendyol')['status'])->toBe('contract_verified')
        ->and($registry->isMarketplaceVerified('trendyol'))->toBeFalse()
        ->and($registry->supports('trendyol', 'stock_publish'))->toBeTrue()
        ->and($registry->supports('trendyol', 'media_manual'))->toBeTrue()
        ->and($registry->supports('trendyol', 'invoice_publish'))->toBeFalse();

    expect($commerce->testConnection((int) $company->getKey(), $connectionPublicId))->toBeTrue();
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/integration/sellers/99999/addresses')
        && str_contains((string) ($request->header('User-Agent')[0] ?? ''), '99999 - MarsOtomasyon')
        && ($request->header('storeFrontCode')[0] ?? null) === 'TR');

    $mappingPublicId = $commerce->mapProduct(
        (int) $company->getKey(),
        $connectionPublicId,
        (int) $product->getKey(),
        '123456789',
        null,
        'TY-STOCK-1',
        ['barcode' => '8680000000001'],
    );

    expect(fn () => $commerce->queueDesiredState(
        (int) $company->getKey(),
        $mappingPublicId,
        '7',
        '125',
        'TRY',
        ['https://cdn.example.test/ty.jpg'],
    ))->toThrow(DomainException::class, 'Trendyol media publishing is manual');

    expect(fn () => $commerce->queueDesiredState(
        (int) $company->getKey(),
        $mappingPublicId,
        '7',
        '125',
        'EUR',
    ))->toThrow(DomainException::class, 'requires TRY');

    $first = $commerce->queueDesiredState((int) $company->getKey(), $mappingPublicId, '7', '125', 'TRY');
    $channels->processSync($first['effect_id']);
    expect(DB::table('integration_sync_effects')->where('id', $first['effect_id'])->value('status'))->toBe('succeeded')
        ->and(DB::table('integration_sync_effects')->where('id', $first['effect_id'])->value('external_id'))->toBe('ty-batch-1')
        ->and($priceCalls)->toBe(1)
        ->and($pricePayload)->toEqual([
            'items' => [[
                'barcode' => '8680000000001',
                'quantity' => 7,
                'salePrice' => 125,
                'listPrice' => 125,
            ]],
        ]);

    $second = $commerce->queueDesiredState((int) $company->getKey(), $mappingPublicId, '7', '125', 'TRY');
    $channels->processSync($second['effect_id']);
    expect(DB::table('integration_sync_effects')->where('id', $second['effect_id'])->value('status'))->toBe('ignored')
        ->and(DB::table('integration_sync_effects')->where('id', $second['effect_id'])->value('ignored_reason'))->toBe('trendyol duplicate stock-price cooldown')
        ->and($priceCalls)->toBe(1)
        ->and((int) DB::table('channel_listing_states')->where('id', $second['state_id'])->value('published_version'))->toBe(2);

    $polled = $commerce->pollOrders((int) $company->getKey(), $connectionPublicId, null, 1, 50);
    expect($polled)->toHaveCount(1);
    $pollEvent = DB::table('integration_events')->where('id', $polled[0])->first();
    $pollBody = json_decode((string) $pollEvent->payload, true, flags: JSON_THROW_ON_ERROR);
    expect((string) $pollEvent->event_type)->toBe('order.created')
        ->and($pollBody['shipmentPackageId'])->toBe(3330111111)
        ->and($pollBody['orderNumber'])->toBe('TY-9001');
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/integration/order/sellers/99999/v2/orders')
        && (int) ($request->data()['page'] ?? -1) === 0
        && (int) ($request->data()['size'] ?? 0) === 50);

    $connectionId = (int) DB::table('integration_connections')->where('public_id', $connectionPublicId)->value('id');
    $webhookOrder = m18TrendyolOrder('TY-9002', 3330111112, 1788100001000, 'Picking');
    $wrapper = [
        'totalElements' => 1,
        'totalPages' => 1,
        'page' => 0,
        'size' => 1,
        'content' => [$webhookOrder],
    ];
    $raw = json_encode($wrapper, JSON_THROW_ON_ERROR);
    $webhookId = $channels->ingestWebhook($connectionId, '', 'order.updated', $raw, 'ty-callback-api-key');
    $webhookReplay = $channels->ingestWebhook($connectionId, '', 'order.updated', $raw, 'ty-callback-api-key');
    $webhookEvent = DB::table('integration_events')->where('id', $webhookId)->first();
    $webhookBody = json_decode((string) $webhookEvent->payload, true, flags: JSON_THROW_ON_ERROR);
    expect($webhookReplay)->toBe($webhookId)
        ->and((string) $webhookEvent->external_event_id)->toBe('ty-webhook-3330111112-1788100001000-picking')
        ->and((string) $webhookEvent->event_type)->toBe('order.picking')
        ->and($webhookBody['orderNumber'])->toBe('TY-9002')
        ->and($webhookBody)->not->toHaveKey('content');

    expect(fn () => $channels->ingestWebhook($connectionId, '', 'order.updated', $raw, 'wrong-key'))
        ->toThrow(DomainException::class, 'signature is invalid');
});

/** @return array{Company, Account, Product} */
function m18TrendyolFixture(): array
{
    $company = Company::query()->create(['code' => 'M18-TY', 'name' => 'M18 Trendyol']);
    $customer = Account::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'M18-TY-CUSTOMER',
        'type' => AccountType::Customer,
        'status' => AccountStatus::Active,
        'legal_name' => 'M18 Trendyol Customer',
        'trade_name' => null,
        'tax_identity_type' => TaxIdentityType::None,
        'tax_number' => null,
        'tax_office' => null,
        'book_currency_code' => 'TRY',
        'due_days' => 0,
        'discount_rate' => '0.000000',
        'risk_limit' => '0.000000',
    ]);
    $category = Category::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'M18-TY-CAT',
        'name' => 'M18 Trendyol',
        'is_active' => true,
    ]);
    $unit = Unit::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'ADET',
        'name' => 'Adet',
        'is_active' => true,
    ]);
    $tax = Tax::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'KDV20',
        'name' => 'KDV %20',
        'rate' => '20.000000',
        'is_active' => true,
    ]);
    $product = Product::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'M18-TY-SKU',
        'status' => ProductStatus::Active,
        'name' => 'M18 Trendyol Product',
        'category_id' => $category->getKey(),
        'unit_id' => $unit->getKey(),
        'tax_id' => $tax->getKey(),
        'sale_price_net' => '125.000000',
        'purchase_price_net' => '80.000000',
    ]);

    return [$company, $customer, $product];
}

/** @return array<string,mixed> */
function m18TrendyolOrder(string $orderNumber, int $packageId, int $lastModifiedDate, string $status): array
{
    return [
        'orderNumber' => $orderNumber,
        'shipmentPackageId' => $packageId,
        'lastModifiedDate' => $lastModifiedDate,
        'status' => $status,
        'shipmentPackageStatus' => $status,
        'orderDate' => 1788099000000,
        'currencyCode' => 'TRY',
        'customerFirstName' => 'Ada',
        'customerLastName' => 'Lovelace',
        'customerEmail' => 'ada@example.test',
        'lines' => [[
            'lineId' => 7001,
            'stockCode' => 'TY-STOCK-1',
            'barcode' => '8680000000001',
            'productName' => 'M18 Trendyol Product',
            'quantity' => 1,
            'salePrice' => 125.0,
            'lineGrossAmount' => 125.0,
            'lineTotalDiscount' => 0.0,
        ]],
    ];
}

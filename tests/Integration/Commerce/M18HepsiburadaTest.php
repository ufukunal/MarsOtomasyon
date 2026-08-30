<?php

use App\Modules\Accounts\Enums\AccountStatus;
use App\Modules\Accounts\Enums\AccountType;
use App\Modules\Accounts\Enums\TaxIdentityType;
use App\Modules\Accounts\Models\Account;
use App\Modules\Commerce\ChannelCenterService;
use App\Modules\Commerce\ProviderRegistry;
use App\Modules\Core\Models\Company;
use DomainException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(DatabaseMigrations::class);

it('tests the Hepsiburada connection and normalizes paid-order details into one order event', function (): void {
    Queue::fake();
    $detailCalls = 0;
    $lines = [
        [
            'id' => 'line-1',
            'sku' => 'HB-SKU-1',
            'merchantSku' => 'MARS-SKU-1',
            'orderId' => 'hb-order-id-1',
            'orderNumber' => 'HB-1001',
            'orderDate' => '2026-08-30T10:15:00+03:00',
            'quantity' => 1,
            'unitPrice' => ['currency' => 'TRY', 'amount' => 125.50],
            'totalPrice' => ['currency' => 'TRY', 'amount' => 125.50],
            'customerName' => 'Ada Lovelace',
            'status' => 'Open',
            'name' => 'Mars Product One',
        ],
        [
            'id' => 'line-2',
            'sku' => 'HB-SKU-2',
            'merchantSku' => 'MARS-SKU-2',
            'orderId' => 'hb-order-id-1',
            'orderNumber' => 'HB-1001',
            'orderDate' => '2026-08-30T10:15:00+03:00',
            'quantity' => 2,
            'unitPrice' => ['currency' => 'TRY', 'amount' => 80],
            'totalPrice' => ['currency' => 'TRY', 'amount' => 160],
            'customerName' => 'Ada Lovelace',
            'status' => 'Open',
            'name' => 'Mars Product Two',
        ],
    ];

    Http::fake(function (Request $request) use (&$detailCalls, $lines) {
        $url = $request->url();
        if (str_contains($url, '/listings/merchantid/merchant-123')) {
            return Http::response(['totalCount' => 0, 'limit' => 1, 'offset' => 0, 'pageCount' => 0, 'items' => []], 200);
        }
        if (str_contains($url, '/orders/merchantid/merchant-123/ordernumber/HB-1001')) {
            $detailCalls++;

            return Http::response($lines, 200);
        }
        if (str_contains($url, '/orders/merchantid/merchant-123')) {
            return Http::response([
                'totalCount' => 2,
                'limit' => 50,
                'offset' => 0,
                'pageCount' => 1,
                'items' => $lines,
            ], 200);
        }

        return Http::response(['unexpected' => $url], 500);
    });

    $company = Company::query()->create(['code' => 'M18-HB', 'name' => 'M18 Hepsiburada']);
    $customer = Account::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'M18-HB-CUSTOMER',
        'type' => AccountType::Customer,
        'status' => AccountStatus::Active,
        'legal_name' => 'M18 Hepsiburada Clearing Customer',
        'trade_name' => null,
        'tax_identity_type' => TaxIdentityType::None,
        'tax_number' => null,
        'tax_office' => null,
        'book_currency_code' => 'TRY',
        'due_days' => 0,
        'discount_rate' => '0.000000',
        'risk_limit' => '0.000000',
    ]);

    $commerce = app(ChannelCenterService::class);
    $registry = app(ProviderRegistry::class);
    $connectionPublicId = $commerce->createConnection(
        companyId: (int) $company->getKey(),
        provider: 'hepsiburada',
        name: 'Hepsiburada SIT',
        baseUrl: null,
        credentials: [
            'merchant_id' => 'merchant-123',
            'username' => 'hb-user',
            'password' => 'hb-password',
            'user_agent' => 'MarsOtomasyon',
            'environment' => 'sit',
            'default_account_id' => (int) $customer->getKey(),
        ],
        webhookSecret: 'hb-webhook-secret',
        financialMode: 'direct_account',
        defaultAccountId: (int) $customer->getKey(),
    );

    expect($registry->isContractVerified('hepsiburada'))->toBeTrue()
        ->and($registry->isMarketplaceVerified('hepsiburada'))->toBeFalse()
        ->and($registry->supports('hepsiburada', 'connection_test'))->toBeTrue()
        ->and($registry->supports('hepsiburada', 'order_polling'))->toBeTrue()
        ->and($registry->supports('hepsiburada', 'stock_publish'))->toBeFalse();

    expect($commerce->testConnection((int) $company->getKey(), $connectionPublicId))->toBeTrue();

    $events = $commerce->pollOrders((int) $company->getKey(), $connectionPublicId, null, 1, 50);
    expect($events)->toHaveCount(1)
        ->and($detailCalls)->toBe(1);

    $event = DB::table('integration_events')->where('id', $events[0])->first();
    $payload = json_decode((string) $event->payload, true, flags: JSON_THROW_ON_ERROR);
    expect((string) $event->event_type)->toBe('order.open')
        ->and((string) $event->external_event_id)->toStartWith('hb-poll-HB-1001-')
        ->and($payload['orderNumber'])->toBe('HB-1001')
        ->and($payload['currencyCode'])->toBe('TRY')
        ->and($payload['billing']['company'])->toBe('Ada Lovelace')
        ->and($payload['lines'])->toHaveCount(2)
        ->and($payload['lines'][0]['merchantSku'])->toBe('MARS-SKU-1')
        ->and($payload['lines'][0]['unitPrice'])->toBe(125.50)
        ->and($payload['lines'][1]['quantity'])->toBe(2);

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/orders/merchantid/merchant-123?')
        && str_contains($request->url(), 'offset=0')
        && str_contains($request->url(), 'limit=50'));
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://oms-external-sit.hepsiburada.com/orders/merchantid/merchant-123/ordernumber/HB-1001');

    expect(fn () => $commerce->pollOrders((int) $company->getKey(), $connectionPublicId, '2026-08-01', 1, 50))
        ->toThrow(DomainException::class, 'date-window polling is disabled');
});

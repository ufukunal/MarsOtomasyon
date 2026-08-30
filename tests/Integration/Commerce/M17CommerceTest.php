<?php

use App\Foundation\Correlation\CorrelationContext;
use App\Foundation\Correlation\CorrelationIdFactory;
use App\Modules\Accounts\Enums\AccountStatus;
use App\Modules\Accounts\Enums\AccountType;
use App\Modules\Accounts\Enums\TaxIdentityType;
use App\Modules\Accounts\Models\Account;
use App\Modules\Commerce\ChannelCenterService;
use App\Modules\Commerce\ProviderRegistry;
use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Core\Enums\DocumentType;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\DocumentSequence;
use App\Modules\Core\Models\Tax;
use App\Modules\Inventory\Actions\PostManualStockMovement;
use App\Modules\Inventory\Enums\ManualStockMovementKind;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Models\WarehouseLocation;
use App\Modules\Operations\ChannelDomainEventIngestor;
use App\Modules\Operations\ChannelService;
use App\Modules\Products\Enums\ProductStatus;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\Unit;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(DatabaseMigrations::class);

it('runs WooCommerce public identity desired state order reservation problem retry return and settlement seams', function (): void {
    Queue::fake();
    Http::fake([
        'https://shop.example.test/wp-json/wc/v3/products/*' => Http::response(['id' => 501], 200),
        'https://shop.example.test/wp-json/wc/v3/system_status' => Http::response(['environment' => []], 200),
    ]);

    [$company, $customer, $clearing, $product, $warehouse, $location] = m17Fixture();
    app(ActiveCompanyContext::class)->set($company);
    $actorId = (int) DB::table('users')->insertGetId([
        'name' => 'M17 Acceptance Actor',
        'email' => 'm17-acceptance@example.test',
        'password' => 'not-used',
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    Auth::loginUsingId($actorId);
    app(CorrelationContext::class)->set(app(CorrelationIdFactory::class)->resolve(null));

    $commerce = app(ChannelCenterService::class);
    $channels = app(ChannelService::class);
    $domain = app(ChannelDomainEventIngestor::class);

    $connectionPublicId = $commerce->createConnection(
        companyId: (int) $company->getKey(),
        provider: 'woocommerce',
        name: 'Primary Woo',
        baseUrl: 'https://shop.example.test',
        credentials: [
            'consumer_key' => 'ck_m17_plain',
            'consumer_secret' => 'cs_m17_plain',
            'price_basis' => 'net',
            'order_series' => 'woo',
            'default_warehouse_id' => (int) $warehouse->getKey(),
            'default_location_id' => (int) $location->getKey(),
        ],
        webhookSecret: 'm17-webhook-secret-value',
        financialMode: 'direct_account',
        defaultAccountId: (int) $customer->getKey(),
    );

    expect($connectionPublicId)->toMatch('/^[0-9A-HJKMNP-TV-Z]{26}$/')
        ->and(app(ProviderRegistry::class)->get('trendyol')['status'])->toBe('transport_only');

    $connection = DB::table('integration_connections')->where('public_id', $connectionPublicId)->first();
    expect($connection)->not->toBeNull()
        ->and((string) $connection->credentials_ciphertext)->not->toContain('ck_m17_plain')
        ->and((string) $connection->credentials_ciphertext)->not->toContain('cs_m17_plain')
        ->and((string) $connection->webhook_secret_ciphertext)->not->toContain('m17-webhook-secret-value')
        ->and(Crypt::decryptString((string) $connection->webhook_secret_ciphertext))->toBe('m17-webhook-secret-value')
        ->and(route('channels.webhook', ['connection' => $connectionPublicId]))->toContain($connectionPublicId);

    expect($commerce->testConnection((int) $company->getKey(), $connectionPublicId))->toBeTrue();

    $mappingPublicId = $commerce->mapProduct(
        (int) $company->getKey(),
        $connectionPublicId,
        (int) $product->getKey(),
        '501',
        null,
        'WOO-SKU-1',
    );
    expect($mappingPublicId)->toMatch('/^[0-9A-HJKMNP-TV-Z]{26}$/');

    $v1 = $commerce->queueDesiredState((int) $company->getKey(), $mappingPublicId, '5', '120', 'TRY', ['https://cdn.example.test/a.jpg']);
    $v2 = $commerce->queueDesiredState((int) $company->getKey(), $mappingPublicId, '7', '125', 'TRY', ['https://cdn.example.test/b.jpg']);
    expect($v1['version'])->toBe(1)->and($v2['version'])->toBe(2);

    $channels->processSync($v1['effect_id']);
    expect(DB::table('integration_sync_effects')->where('id', $v1['effect_id'])->value('status'))->toBe('ignored')
        ->and(DB::table('integration_sync_effects')->where('id', $v1['effect_id'])->value('ignored_reason'))->toBe('stale desired-state version');

    $channels->processSync($v2['effect_id']);
    $state = DB::table('channel_listing_states')->where('id', $v2['state_id'])->first();
    expect($state)->not->toBeNull()
        ->and((int) $state->desired_version)->toBe(2)
        ->and((int) $state->published_version)->toBe(2)
        ->and((string) $state->published_stock)->toBe('7.000000')
        ->and((string) $state->published_price)->toBe('125.000000')
        ->and((string) $state->status)->toBe('synced');

    app(PostManualStockMovement::class)->handle(
        'm17-opening-1',
        (int) $product->getKey(),
        (int) $warehouse->getKey(),
        (int) $location->getKey(),
        ManualStockMovementKind::OpeningIn,
        '5',
        '80',
        'M17 acceptance opening stock',
    );

    $firstPayload = m17WooOrderPayload(9001, 'WOO-SKU-1', 3, '120.000000', 'first@example.test');
    $firstEventId = m17IngestWoo($channels, (int) $connection->id, 'evt-m17-9001', $firstPayload, 'm17-webhook-secret-value');
    $first = $domain->process($firstEventId);
    $firstReplay = $domain->process($firstEventId);

    expect($first)->not->toBeNull()
        ->and($firstReplay)->not->toBeNull()
        ->and($firstReplay['local_id'])->toBe($first['local_id'])
        ->and(DB::table('sales_orders')->where('company_id', $company->getKey())->count())->toBe(1)
        ->and(DB::table('stock_reservations')->where('company_id', $company->getKey())->count())->toBe(1)
        ->and(DB::table('sales_order_reservation_generations')->where('sales_order_id', $first['local_id'])->count())->toBe(1);

    $firstInbox = DB::table('channel_order_inbox')->where('external_order_id', '9001')->first();
    $firstSnapshot = json_decode((string) $firstInbox->customer_snapshot, true, flags: JSON_THROW_ON_ERROR);
    expect((string) $firstInbox->status)->toBe('imported')
        ->and($firstSnapshot['billing']['email'])->toBe('first@example.test');

    $ordersBeforeProblem = DB::table('sales_orders')->where('company_id', $company->getKey())->count();
    $reservationsBeforeProblem = DB::table('stock_reservations')->where('company_id', $company->getKey())->count();
    $secondPayload = m17WooOrderPayload(9002, 'WOO-SKU-1', 4, '120.000000', 'second@example.test');
    $secondEventId = m17IngestWoo($channels, (int) $connection->id, 'evt-m17-9002', $secondPayload, 'm17-webhook-secret-value');

    expect($domain->process($secondEventId))->toBeNull();
    $problemInbox = DB::table('channel_order_inbox')->where('external_order_id', '9002')->first();
    expect((string) $problemInbox->status)->toBe('stock_problem')
        ->and((string) $problemInbox->problem_code)->toBe('stock_problem')
        ->and(DB::table('sales_orders')->where('company_id', $company->getKey())->count())->toBe($ordersBeforeProblem)
        ->and(DB::table('stock_reservations')->where('company_id', $company->getKey())->count())->toBe($reservationsBeforeProblem)
        ->and(DB::table('channel_problems')->where('order_inbox_id', $problemInbox->id)->where('status', 'open')->count())->toBe(1)
        ->and((string) DB::table('stock_balances')
            ->where('company_id', $company->getKey())
            ->where('product_id', $product->getKey())
            ->where('warehouse_id', $warehouse->getKey())
            ->where('location_id', $location->getKey())
            ->value('quantity'))->toBe('5.000000');

    app(PostManualStockMovement::class)->handle(
        'm17-opening-2',
        (int) $product->getKey(),
        (int) $warehouse->getKey(),
        (int) $location->getKey(),
        ManualStockMovementKind::AdjustmentIn,
        '5',
        '80',
        'M17 retry stock',
    );
    $commerce->retryOrder((int) $company->getKey(), (string) $problemInbox->public_id);
    $retry = $domain->process($secondEventId);
    expect($retry)->not->toBeNull()
        ->and(DB::table('sales_orders')->where('company_id', $company->getKey())->count())->toBe($ordersBeforeProblem + 1)
        ->and(DB::table('stock_reservations')->where('company_id', $company->getKey())->count())->toBe($reservationsBeforeProblem + 1)
        ->and(DB::table('channel_order_inbox')->where('id', $problemInbox->id)->value('status'))->toBe('imported')
        ->and(DB::table('channel_problems')->where('order_inbox_id', $problemInbox->id)->where('status', 'open')->count())->toBe(0);

    $returnId = $commerce->recordReturnEvidence(
        (int) $company->getKey(),
        $connectionPublicId,
        'RET-9001',
        '9001',
        ['reason' => 'customer_return', 'quantity' => 1],
    );
    expect(DB::table('channel_return_events')->where('id', $returnId)->value('status'))->toBe('awaiting_invoice')
        ->and(DB::table('sales_returns')->where('company_id', $company->getKey())->count())->toBe(0);

    $clearingPublicId = $commerce->createConnection(
        companyId: (int) $company->getKey(),
        provider: 'woocommerce',
        name: 'Woo Clearing',
        baseUrl: 'https://shop.example.test',
        credentials: ['consumer_key' => 'ck_clear', 'consumer_secret' => 'cs_clear', 'price_basis' => 'net', 'order_series' => 'woo'],
        webhookSecret: 'm17-clearing-webhook-secret',
        financialMode: 'clearing_account',
        clearingAccountId: (int) $clearing->getKey(),
    );
    $settlementId = $commerce->recordSettlementEvidence(
        (int) $company->getKey(),
        $clearingPublicId,
        'SET-1',
        'TRY',
        '100',
        '10',
        '2026-08-30T07:00:00+03:00',
        ['provider' => 'woocommerce'],
    );
    $settlement = DB::table('channel_settlement_evidence')->where('id', $settlementId)->first();
    expect((string) $settlement->gross_amount)->toBe('100.000000')
        ->and((string) $settlement->fee_amount)->toBe('10.000000')
        ->and((string) $settlement->net_amount)->toBe('90.000000')
        ->and((string) $settlement->status)->toBe('received');

    $commerce->markSettlementHandedOff((int) $company->getKey(), (string) $settlement->public_id);
    expect(DB::table('channel_settlement_evidence')->where('id', $settlementId)->value('status'))->toBe('handed_off');
});

/** @return array{Company, Account, Account, Product, Warehouse, WarehouseLocation} */
function m17Fixture(): array
{
    $company = Company::query()->create(['code' => 'M17-COMPANY', 'name' => 'M17 Company']);
    $customer = Account::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'M17-CUSTOMER',
        'type' => AccountType::Customer,
        'status' => AccountStatus::Active,
        'legal_name' => 'M17 Customer',
        'trade_name' => null,
        'tax_identity_type' => TaxIdentityType::None,
        'tax_number' => null,
        'tax_office' => null,
        'book_currency_code' => 'TRY',
        'due_days' => 0,
        'discount_rate' => '0.000000',
        'risk_limit' => '0.000000',
    ]);
    $clearing = Account::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'M17-CLEARING',
        'type' => AccountType::Clearing,
        'status' => AccountStatus::Active,
        'legal_name' => 'M17 Channel Clearing',
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
        'code' => 'M17-CAT',
        'name' => 'M17 Category',
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
        'code' => 'M17-KDV20',
        'name' => 'KDV %20',
        'rate' => '20.000000',
        'is_active' => true,
    ]);
    $product = Product::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'M17-PRODUCT',
        'status' => ProductStatus::Active,
        'name' => 'M17 Product',
        'category_id' => $category->getKey(),
        'unit_id' => $unit->getKey(),
        'tax_id' => $tax->getKey(),
        'sale_price_net' => '120.000000',
        'purchase_price_net' => '80.000000',
    ]);
    $warehouse = Warehouse::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'M17-WH',
        'name' => 'M17 Warehouse',
        'is_active' => true,
    ]);
    $location = WarehouseLocation::query()->create([
        'company_id' => $company->getKey(),
        'warehouse_id' => $warehouse->getKey(),
        'code' => 'M17-LOC',
        'name' => 'M17 Location',
        'is_active' => true,
    ]);
    DocumentSequence::query()->create([
        'company_id' => $company->getKey(),
        'document_type' => DocumentType::SalesOrder,
        'series_code' => 'woo',
        'prefix' => 'WOO-',
        'padding' => 6,
        'next_value' => 1,
        'is_active' => true,
    ]);

    return [$company, $customer, $clearing, $product, $warehouse, $location];
}

/** @return array<string,mixed> */
function m17WooOrderPayload(int $id, string $sku, int $quantity, string $price, string $email): array
{
    return [
        'id' => $id,
        'currency' => 'TRY',
        'date_created' => '2026-08-30T06:30:00+03:00',
        'billing' => [
            'first_name' => 'Mars',
            'last_name' => 'Customer',
            'email' => $email,
            'phone' => '+905551112233',
            'country' => 'TR',
        ],
        'shipping' => ['city' => 'Istanbul', 'country' => 'TR'],
        'line_items' => [[
            'product_id' => 501,
            'sku' => $sku,
            'name' => 'M17 Product',
            'quantity' => $quantity,
            'price' => $price,
        ]],
    ];
}

/** @param array<string,mixed> $payload */
function m17IngestWoo(ChannelService $channels, int $connectionId, string $eventId, array $payload, string $secret): int
{
    $raw = json_encode($payload, JSON_THROW_ON_ERROR);
    $signature = base64_encode(hash_hmac('sha256', $raw, $secret, true));

    return $channels->ingestWebhook($connectionId, $eventId, 'order.created', $raw, $signature);
}

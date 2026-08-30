<?php

use App\Modules\Accounts\Enums\AccountStatus;
use App\Modules\Accounts\Enums\AccountType;
use App\Modules\Accounts\Enums\TaxIdentityType;
use App\Modules\Accounts\Models\Account;
use App\Modules\Core\Enums\DocumentType;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\DocumentSequence;
use App\Modules\Core\Models\Tax;
use App\Modules\Operations\ChannelDomainEventIngestor;
use App\Modules\Operations\ChannelService;
use App\Modules\Products\Enums\ProductStatus;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\Unit;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

uses(DatabaseMigrations::class);

it('maps a Trendyol order into one idempotently linked Mars sales order by merchant SKU', function (): void {
    Queue::fake();
    [$company, $customer, $product] = m11TrendyolFixture();

    DocumentSequence::query()->create([
        'company_id' => $company->getKey(),
        'document_type' => DocumentType::SalesOrder,
        'series_code' => 'trendyol',
        'prefix' => 'TY-',
        'padding' => 6,
        'next_value' => 1,
        'is_active' => true,
    ]);

    $channels = app(ChannelService::class);
    $connectionId = $channels->createConnection(
        (int) $company->getKey(),
        'trendyol',
        'Trendyol Shop',
        null,
        [
            'seller_id' => '99999',
            'api_key' => 'm11-api-key',
            'api_secret' => 'm11-api-secret',
            'integration_name' => 'MarsOtomasyon',
            'environment' => 'stage',
            'webhook_authentication_type' => 'API_KEY',
            'default_account_id' => (int) $customer->getKey(),
            'price_basis' => 'gross',
            'order_series' => 'trendyol',
        ],
        'm11-trendyol-webhook-secret',
    );
    $payload = [
        'orderNumber' => 'TY-9002',
        'currencyCode' => 'TRY',
        'orderDate' => 1787898600000,
        'lines' => [[
            'merchantSku' => (string) $product->code,
            'productName' => (string) $product->name,
            'quantity' => 3,
            'salePrice' => '144.000000',
        ]],
    ];
    $raw = json_encode($payload, JSON_THROW_ON_ERROR);
    $signature = 'm11-trendyol-webhook-secret';
    $eventId = $channels->ingestWebhook($connectionId, 'trendyol-order-9002', 'order.created', $raw, $signature);
    $domain = app(ChannelDomainEventIngestor::class);

    $first = $domain->process($eventId);
    $replay = $domain->process($eventId);

    expect($first)->not->toBeNull()
        ->and($replay)->not->toBeNull()
        ->and($replay['local_id'])->toBe($first['local_id'])
        ->and(DB::table('sales_orders')->where('company_id', $company->getKey())->count())->toBe(1)
        ->and(DB::table('integration_entity_links')
            ->where('company_id', $company->getKey())
            ->where('connection_id', $connectionId)
            ->where('entity_type', 'order')
            ->where('external_id', 'TY-9002')
            ->count())->toBe(1)
        ->and(DB::table('sales_order_lines')->where('sales_order_id', $first['local_id'])->value('quantity'))->toBe('3.000000');
});

/** @return array{Company, Account, Product} */
function m11TrendyolFixture(): array
{
    $company = Company::query()->create(['code' => 'M11-TRENDYOL', 'name' => 'M11 Trendyol']);
    $customer = Account::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'TY-CUSTOMER',
        'type' => AccountType::Customer,
        'status' => AccountStatus::Active,
        'legal_name' => 'Trendyol Customer',
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
        'code' => 'TY-CAT',
        'name' => 'Trendyol',
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
        'code' => 'TY-SKU-1',
        'status' => ProductStatus::Active,
        'name' => 'Trendyol Product',
        'category_id' => $category->getKey(),
        'unit_id' => $unit->getKey(),
        'tax_id' => $tax->getKey(),
        'sale_price_net' => '120.000000',
        'purchase_price_net' => '80.000000',
    ]);

    return [$company, $customer, $product];
}

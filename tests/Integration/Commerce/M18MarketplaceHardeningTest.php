<?php

use App\Foundation\Correlation\CorrelationContext;
use App\Foundation\Correlation\CorrelationIdFactory;
use App\Modules\Accounts\Enums\AccountStatus;
use App\Modules\Accounts\Enums\AccountType;
use App\Modules\Accounts\Enums\TaxIdentityType;
use App\Modules\Accounts\Models\Account;
use App\Modules\Commerce\ChannelCenterService;
use App\Modules\Commerce\MarketplacePack\MarketplacePackService;
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
use App\Modules\Products\Enums\ProductStatus;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\Unit;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(DatabaseMigrations::class);

it('rejects malformed marketplace order records without persisting partial events', function (): void {
    Queue::fake();
    Http::fake([
        'https://api.n11.com/rest/delivery/v1/shipmentPackages*' => Http::response([
            'content' => ['malformed-record'],
            'totalPages' => 1,
        ], 200),
    ]);

    [$company, $customer] = m18HardeningFixture();
    $connectionPublicId = app(ChannelCenterService::class)->createConnection(
        companyId: (int) $company->getKey(),
        provider: 'n11',
        name: 'n11 Malformed Fixture',
        baseUrl: null,
        credentials: [
            'app_key' => 'n11-app-key',
            'app_secret' => 'n11-app-secret',
            'integrator' => 'MarsOtomasyon',
        ],
        webhookSecret: 'n11-hardening-webhook-secret',
        financialMode: 'direct_account',
        defaultAccountId: (int) $customer->getKey(),
    );

    expect(fn () => app(MarketplacePackService::class)->pollOrders(
        (int) $company->getKey(),
        $connectionPublicId,
        '2026-08-30T00:00:00+03:00',
        1,
        50,
    ))->toThrow(RuntimeException::class, 'Marketplace order response contains an invalid record.');

    expect(DB::table('integration_events')->count())->toBe(0);
});

it('routes marketplace stock reservation failures into Problem Center', function (): void {
    Queue::fake();
    Http::fake([
        'https://api.n11.com/rest/delivery/v1/shipmentPackages*' => Http::response([
            'content' => [[
                'orderNumber' => 'N11-PROBLEM-1',
                'shipmentPackageStatus' => 'Created',
                'currency' => 'TRY',
                'lines' => [[
                    'stockCode' => 'M18-HARD-SKU',
                    'name' => 'M18 Hardening Product',
                    'quantity' => 2,
                    'price' => 125,
                ]],
            ]],
            'totalPages' => 1,
        ], 200),
    ]);

    [$company, $customer, $product, $warehouse, $location] = m18HardeningFixture();
    app(ActiveCompanyContext::class)->set($company);
    $actorId = (int) DB::table('users')->insertGetId([
        'name' => 'M18 Hardening Actor',
        'email' => 'm18-hardening@example.test',
        'password' => 'not-used',
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    Auth::loginUsingId($actorId);
    app(CorrelationContext::class)->set(app(CorrelationIdFactory::class)->resolve(null));

    app(PostManualStockMovement::class)->handle(
        'm18-hardening-opening',
        (int) $product->getKey(),
        (int) $warehouse->getKey(),
        (int) $location->getKey(),
        ManualStockMovementKind::OpeningIn,
        '1',
        '80',
        'M18 hardening opening stock',
    );

    $center = app(ChannelCenterService::class);
    $connectionPublicId = $center->createConnection(
        companyId: (int) $company->getKey(),
        provider: 'n11',
        name: 'n11 Problem Center',
        baseUrl: null,
        credentials: [
            'app_key' => 'n11-app-key',
            'app_secret' => 'n11-app-secret',
            'integrator' => 'MarsOtomasyon',
            'price_basis' => 'net',
            'order_series' => 'n11',
            'default_warehouse_id' => (int) $warehouse->getKey(),
            'default_location_id' => (int) $location->getKey(),
        ],
        webhookSecret: 'n11-problem-webhook-secret',
        financialMode: 'direct_account',
        defaultAccountId: (int) $customer->getKey(),
    );
    $center->mapProduct(
        (int) $company->getKey(),
        $connectionPublicId,
        (int) $product->getKey(),
        null,
        null,
        'M18-HARD-SKU',
    );

    $eventIds = app(MarketplacePackService::class)->pollOrders(
        (int) $company->getKey(),
        $connectionPublicId,
        '2026-08-30T00:00:00+03:00',
        1,
        50,
    );
    expect($eventIds)->toHaveCount(1);

    expect(app(ChannelDomainEventIngestor::class)->process($eventIds[0]))->toBeNull();

    $inbox = DB::table('channel_order_inbox')->where('external_order_id', 'N11-PROBLEM-1')->first();
    expect($inbox)->not->toBeNull()
        ->and((string) $inbox->status)->toBe('stock_problem')
        ->and((string) $inbox->problem_code)->toBe('stock_problem')
        ->and(DB::table('channel_problems')->where('order_inbox_id', $inbox->id)->where('status', 'open')->count())->toBe(1)
        ->and(DB::table('sales_orders')->where('company_id', $company->getKey())->count())->toBe(0)
        ->and((string) DB::table('stock_balances')
            ->where('company_id', $company->getKey())
            ->where('product_id', $product->getKey())
            ->where('warehouse_id', $warehouse->getKey())
            ->where('location_id', $location->getKey())
            ->value('quantity'))->toBe('1.000000');
});

/** @return array{Company,Account,Product,Warehouse,WarehouseLocation} */
function m18HardeningFixture(): array
{
    $company = Company::query()->create(['code' => 'M18-HARD', 'name' => 'M18 Hardening']);
    $customer = Account::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'M18-HARD-CUSTOMER',
        'type' => AccountType::Customer,
        'status' => AccountStatus::Active,
        'legal_name' => 'M18 Hardening Customer',
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
        'code' => 'M18-HARD-CAT',
        'name' => 'M18 Hardening',
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
        'code' => 'M18-HARD-KDV20',
        'name' => 'KDV %20',
        'rate' => '20.000000',
        'is_active' => true,
    ]);
    $product = Product::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'M18-HARD-SKU',
        'status' => ProductStatus::Active,
        'name' => 'M18 Hardening Product',
        'category_id' => $category->getKey(),
        'unit_id' => $unit->getKey(),
        'tax_id' => $tax->getKey(),
        'sale_price_net' => '125.000000',
        'purchase_price_net' => '80.000000',
    ]);
    $warehouse = Warehouse::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'M18-HARD-WH',
        'name' => 'M18 Hardening Warehouse',
        'is_active' => true,
    ]);
    $location = WarehouseLocation::query()->create([
        'company_id' => $company->getKey(),
        'warehouse_id' => $warehouse->getKey(),
        'code' => 'M18-HARD-LOC',
        'name' => 'M18 Hardening Location',
        'is_active' => true,
    ]);
    DocumentSequence::query()->create([
        'company_id' => $company->getKey(),
        'document_type' => DocumentType::SalesOrder,
        'series_code' => 'n11',
        'prefix' => 'N11-',
        'padding' => 6,
        'next_value' => 1,
        'is_active' => true,
    ]);

    return [$company, $customer, $product, $warehouse, $location];
}

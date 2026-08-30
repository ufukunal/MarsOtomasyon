<?php

use App\Modules\Accounts\Enums\AccountStatus;
use App\Modules\Accounts\Enums\AccountType;
use App\Modules\Accounts\Enums\TaxIdentityType;
use App\Modules\Accounts\Models\Account;
use App\Modules\Commerce\ChannelCenterService;
use App\Modules\Commerce\MarketplacePack\MarketplacePackService;
use App\Modules\Commerce\ProviderRegistry;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Tax;
use App\Modules\Products\Enums\ProductStatus;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\Unit;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(DatabaseMigrations::class);

it('runs the verified marketplace pack lifecycle with stale and duplicate guards', function (): void {
    Queue::fake();
    $publishCalls = 0;

    Http::fake(function (Request $request) use (&$publishCalls) {
        if (str_contains($request->url(), '/ms/product-query')) {
            return Http::response(['content' => [], 'totalPages' => 0], 200);
        }
        if (str_contains($request->url(), '/ms/product/tasks/price-stock-update')) {
            $publishCalls++;

            return Http::response(['id' => 18001, 'type' => 'SKU_UPDATE', 'status' => 'IN_QUEUE'], 200);
        }
        if (str_contains($request->url(), '/rest/delivery/v1/shipmentPackages')) {
            return Http::response([
                'content' => [[
                    'orderNumber' => 'N11-9001',
                    'shipmentPackageStatus' => 'Created',
                    'lastModifiedDate' => 1788120000000,
                    'lines' => [['stockCode' => 'N11-SKU-1', 'quantity' => 1, 'price' => 125]],
                ]],
                'totalPages' => 1,
            ], 200);
        }

        return Http::response(['unexpected' => $request->url()], 500);
    });

    [$company, $customer, $product] = m18PackFixture();
    $center = app(ChannelCenterService::class);
    $pack = app(MarketplacePackService::class);
    $registry = app(ProviderRegistry::class);

    foreach (['trendyol', 'hepsiburada', 'amazon', 'n11', 'pttavm', 'idefix', 'allesgo'] as $provider) {
        expect($registry->isContractVerified($provider))->toBeTrue()
            ->and($registry->isMarketplaceVerified($provider))->toBeFalse();
    }

    $connectionPublicId = $center->createConnection(
        companyId: (int) $company->getKey(),
        provider: 'n11',
        name: 'n11 Contract',
        baseUrl: null,
        credentials: [
            'app_key' => 'n11-app-key',
            'app_secret' => 'n11-app-secret',
            'integrator' => 'MarsOtomasyon',
        ],
        webhookSecret: 'n11-not-used-webhook-secret',
        financialMode: 'direct_account',
        defaultAccountId: (int) $customer->getKey(),
    );

    expect($pack->testConnection((int) $company->getKey(), $connectionPublicId))->toBeTrue();
    $ciphertext = (string) DB::table('integration_connections')->where('public_id', $connectionPublicId)->value('credentials_ciphertext');
    expect($ciphertext)->not->toContain('n11-app-key')->not->toContain('n11-app-secret');

    $mappingPublicId = $center->mapProduct(
        (int) $company->getKey(),
        $connectionPublicId,
        (int) $product->getKey(),
        null,
        null,
        'N11-SKU-1',
    );

    $stale = $pack->queueDesiredState((int) $company->getKey(), $mappingPublicId, '4', '120', 'TRY');
    $current = $pack->queueDesiredState((int) $company->getKey(), $mappingPublicId, '5', '125', 'TRY');
    $pack->processSync($stale['effect_id']);
    expect(DB::table('integration_sync_effects')->where('id', $stale['effect_id'])->value('status'))->toBe('ignored')
        ->and(DB::table('integration_sync_effects')->where('id', $stale['effect_id'])->value('ignored_reason'))->toBe('stale desired-state version');

    $pack->processSync($current['effect_id']);
    expect(DB::table('integration_sync_effects')->where('id', $current['effect_id'])->value('status'))->toBe('succeeded')
        ->and(DB::table('integration_sync_effects')->where('id', $current['effect_id'])->value('external_id'))->toBe('18001')
        ->and($publishCalls)->toBe(1)
        ->and((int) DB::table('channel_listing_states')->where('id', $current['state_id'])->value('published_version'))->toBe(2);

    $duplicate = $pack->queueDesiredState((int) $company->getKey(), $mappingPublicId, '5', '125', 'TRY');
    $pack->processSync($duplicate['effect_id']);
    expect(DB::table('integration_sync_effects')->where('id', $duplicate['effect_id'])->value('status'))->toBe('ignored')
        ->and(DB::table('integration_sync_effects')->where('id', $duplicate['effect_id'])->value('ignored_reason'))->toBe('marketplace duplicate desired-state cooldown')
        ->and($publishCalls)->toBe(1)
        ->and((int) DB::table('channel_listing_states')->where('id', $duplicate['state_id'])->value('published_version'))->toBe(3);

    $firstPoll = $pack->pollOrders((int) $company->getKey(), $connectionPublicId, '2026-08-30T00:00:00+03:00', 1, 50);
    $secondPoll = $pack->pollOrders((int) $company->getKey(), $connectionPublicId, '2026-08-30T00:00:00+03:00', 1, 50);
    expect($firstPoll)->toHaveCount(1)
        ->and($secondPoll)->toEqual($firstPoll)
        ->and(DB::table('integration_events')->where('id', $firstPoll[0])->value('event_type'))->toBe('order.created');

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/ms/product/tasks/price-stock-update')
        && ($request->header('appkey')[0] ?? null) === 'n11-app-key'
        && ($request->data()['payload']['skus'][0]['stockCode'] ?? null) === 'N11-SKU-1'
        && ($request->data()['payload']['skus'][0]['quantity'] ?? null) === 5);
});

/** @return array{Company,Account,Product} */
function m18PackFixture(): array
{
    $company = Company::query()->create(['code' => 'M18-PACK', 'name' => 'M18 Marketplace Pack']);
    $customer = Account::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'M18-PACK-CUSTOMER',
        'type' => AccountType::Customer,
        'status' => AccountStatus::Active,
        'legal_name' => 'M18 Marketplace Customer',
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
        'code' => 'M18-PACK-CAT',
        'name' => 'M18 Marketplace',
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
        'code' => 'M18-PACK-KDV20',
        'name' => 'KDV %20',
        'rate' => '20.000000',
        'is_active' => true,
    ]);
    $product = Product::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'M18-PACK-SKU',
        'status' => ProductStatus::Active,
        'name' => 'M18 Marketplace Product',
        'category_id' => $category->getKey(),
        'unit_id' => $unit->getKey(),
        'tax_id' => $tax->getKey(),
        'sale_price_net' => '125.000000',
        'purchase_price_net' => '80.000000',
    ]);

    return [$company, $customer, $product];
}

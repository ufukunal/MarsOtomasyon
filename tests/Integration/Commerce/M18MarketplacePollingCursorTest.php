<?php

use App\Modules\Accounts\Enums\AccountStatus;
use App\Modules\Accounts\Enums\AccountType;
use App\Modules\Accounts\Enums\TaxIdentityType;
use App\Modules\Accounts\Models\Account;
use App\Modules\Commerce\ChannelCenterService;
use App\Modules\Commerce\MarketplacePack\MarketplacePackService;
use App\Modules\Core\Models\Company;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

uses(DatabaseMigrations::class);

it('resumes n11 order polling from the persisted page cursor after restart', function (): void {
    $requests = [];
    Http::fake(function (Request $request) use (&$requests) {
        if (! str_contains($request->url(), '/rest/delivery/v1/shipmentPackages')) {
            return Http::response([], 500);
        }

        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
        $requests[] = $query;
        $page = (int) ($query['page'] ?? -1);

        return Http::response([
            'content' => [[
                'orderNumber' => $page === 0 ? 'N11-CURSOR-1' : 'N11-CURSOR-2',
                'shipmentPackageStatus' => 'Created',
            ]],
            'totalPages' => 2,
        ], 200);
    });

    [$company, $customer] = m18PollingCursorFixture('N11-CURSOR');
    $connectionPublicId = app(ChannelCenterService::class)->createConnection(
        companyId: (int) $company->getKey(),
        provider: 'n11',
        name: 'n11 Cursor',
        baseUrl: null,
        credentials: [
            'app_key' => 'n11-key',
            'app_secret' => 'n11-secret',
            'integrator' => 'MarsOtomasyon',
        ],
        webhookSecret: 'n11-cursor-webhook',
        financialMode: 'direct_account',
        defaultAccountId: (int) $customer->getKey(),
    );

    expect(app(MarketplacePackService::class)->pollOrders(
        (int) $company->getKey(),
        $connectionPublicId,
        null,
        1,
        1,
    ))->toHaveCount(1);

    $connection = DB::table('integration_connections')->where('public_id', $connectionPublicId)->first();
    $cursor = json_decode((string) $connection->order_poll_cursor, true, flags: JSON_THROW_ON_ERROR);
    expect($cursor['page'])->toBe(2)
        ->and($cursor['pagination_token'])->toBeNull()
        ->and($connection->order_poll_watermark_at)->toBeNull();

    expect(app(MarketplacePackService::class)->pollOrders(
        (int) $company->getKey(),
        $connectionPublicId,
        null,
        1,
        1,
    ))->toHaveCount(1);

    $connection = DB::table('integration_connections')->where('public_id', $connectionPublicId)->first();
    expect($connection->order_poll_cursor)->toBeNull()
        ->and($connection->order_poll_watermark_at)->not->toBeNull()
        ->and(DB::table('integration_events')->where('connection_id', $connection->id)->count())->toBe(2)
        ->and($requests)->toHaveCount(2)
        ->and((int) $requests[0]['page'])->toBe(0)
        ->and((int) $requests[1]['page'])->toBe(1)
        ->and((string) $requests[1]['startDate'])->toBe((string) $requests[0]['startDate'])
        ->and((string) $requests[1]['endDate'])->toBe((string) $requests[0]['endDate']);
});

it('resumes Amazon Orders 2026 with paginationToken and parses orderId', function (): void {
    $queries = [];
    Http::fake(function (Request $request) use (&$queries) {
        if (! str_contains($request->url(), '/orders/2026-01-01/orders')) {
            return Http::response([], 500);
        }

        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
        $queries[] = $query;
        if (($query['paginationToken'] ?? null) === 'AMZ-NEXT-2') {
            return Http::response(['orders' => []], 200);
        }

        return Http::response([
            'orders' => [[
                'orderId' => 'AMZ-ORDER-2026-1',
                'status' => 'UNSHIPPED',
            ]],
            'pagination' => ['nextToken' => 'AMZ-NEXT-2'],
        ], 200);
    });

    [$company, $customer] = m18PollingCursorFixture('AMZ-CURSOR');
    $connectionPublicId = app(ChannelCenterService::class)->createConnection(
        companyId: (int) $company->getKey(),
        provider: 'amazon',
        name: 'Amazon Cursor',
        baseUrl: null,
        credentials: [
            'seller_id' => 'A1SELLER',
            'marketplace_id' => 'A1MARKETPLACE',
            'region' => 'eu',
            'environment' => 'sandbox',
            'access_token' => 'Atza|cursor-token',
            'user_agent' => 'MarsOtomasyon/1.0',
        ],
        webhookSecret: 'amazon-cursor-webhook',
        financialMode: 'direct_account',
        defaultAccountId: (int) $customer->getKey(),
    );

    expect(app(MarketplacePackService::class)->pollOrders(
        (int) $company->getKey(),
        $connectionPublicId,
        null,
        1,
        1,
    ))->toHaveCount(1);

    $connection = DB::table('integration_connections')->where('public_id', $connectionPublicId)->first();
    $cursor = json_decode((string) $connection->order_poll_cursor, true, flags: JSON_THROW_ON_ERROR);
    expect($cursor['pagination_token'])->toBe('AMZ-NEXT-2')
        ->and($connection->order_poll_watermark_at)->toBeNull();

    expect(app(MarketplacePackService::class)->pollOrders(
        (int) $company->getKey(),
        $connectionPublicId,
        null,
        1,
        1,
    ))->toBe([]);

    $connection = DB::table('integration_connections')->where('public_id', $connectionPublicId)->first();
    expect($connection->order_poll_cursor)->toBeNull()
        ->and($connection->order_poll_watermark_at)->not->toBeNull()
        ->and(DB::table('integration_events')->where('connection_id', $connection->id)->count())->toBe(1)
        ->and(DB::table('integration_events')->where('connection_id', $connection->id)->value('external_event_id'))->toContain('AMZ-ORDER-2026-1')
        ->and($queries)->toHaveCount(2)
        ->and($queries[0])->not->toHaveKey('paginationToken')
        ->and($queries[1]['paginationToken'])->toBe('AMZ-NEXT-2')
        ->and($queries[1]['lastUpdatedAfter'])->toBe($queries[0]['lastUpdatedAfter'])
        ->and($queries[1]['lastUpdatedBefore'])->toBe($queries[0]['lastUpdatedBefore'])
        ->and((int) $queries[1]['maxResultsPerPage'])->toBe(1);
});

/** @return array{Company,Account} */
function m18PollingCursorFixture(string $code): array
{
    $company = Company::query()->create([
        'code' => $code,
        'name' => $code,
    ]);
    $customer = Account::query()->create([
        'company_id' => $company->getKey(),
        'code' => $code.'-CUSTOMER',
        'type' => AccountType::Customer,
        'status' => AccountStatus::Active,
        'legal_name' => $code.' Customer',
        'trade_name' => null,
        'tax_identity_type' => TaxIdentityType::None,
        'tax_number' => null,
        'tax_office' => null,
        'book_currency_code' => 'TRY',
        'due_days' => 0,
        'discount_rate' => '0.000000',
        'risk_limit' => '0.000000',
    ]);

    return [$company, $customer];
}

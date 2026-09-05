<?php

use App\Modules\Accounts\Enums\AccountStatus;
use App\Modules\Accounts\Enums\AccountType;
use App\Modules\Accounts\Enums\TaxIdentityType;
use App\Modules\Accounts\Models\Account;
use App\Modules\Commerce\ChannelCenterService;
use App\Modules\Commerce\ProviderRegistry;
use App\Modules\Core\Models\Company;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;

uses(DatabaseMigrations::class);

it('publishes Mars-owned lifecycle metadata for the verified marketplace adapters', function (): void {
    $registry = app(ProviderRegistry::class);

    foreach (['trendyol', 'hepsiburada', 'n11'] as $provider) {
        expect($registry->lifecycle($provider))->toBe([
            'contract_version' => '1.0',
            'deprecated_after' => null,
        ]);
    }
});

it('keeps settlement recording replay-safe and rejects payload drift', function (): void {
    [$company, $clearing] = m23SettlementCompany('M23-SET-A', AccountType::Clearing);
    $service = app(ChannelCenterService::class);
    $connection = m23ClearingConnection($service, $company, $clearing, 'Replay Clearing');

    $first = $service->recordSettlementEvidence(
        (int) $company->getKey(),
        $connection,
        'M23-SETTLEMENT-1',
        'TRY',
        '125.500000',
        '25.500000',
        '2026-09-04T12:00:00+03:00',
        ['batch' => 'A'],
    );
    $replay = $service->recordSettlementEvidence(
        (int) $company->getKey(),
        $connection,
        'M23-SETTLEMENT-1',
        'TRY',
        '125.500000',
        '25.500000',
        '2026-09-04T12:00:00+03:00',
        ['batch' => 'A'],
    );

    expect($replay)->toBe($first)
        ->and(DB::table('channel_settlement_evidence')->where('id', $first)->value('net_amount'))->toBe('100.000000')
        ->and(DB::table('channel_settlement_evidence')->where('external_settlement_id', 'M23-SETTLEMENT-1')->count())->toBe(1);

    expect(fn () => $service->recordSettlementEvidence(
        (int) $company->getKey(),
        $connection,
        'M23-SETTLEMENT-1',
        'TRY',
        '125.500000',
        '25.500000',
        '2026-09-04T12:00:00+03:00',
        ['batch' => 'DRIFT'],
    ))->toThrow(DomainException::class, 'replay drift');
});

it('rejects a clearing account that belongs to another company at settlement write time', function (): void {
    [$company, $clearing] = m23SettlementCompany('M23-SET-B', AccountType::Clearing);
    [$otherCompany, $otherClearing] = m23SettlementCompany('M23-SET-C', AccountType::Clearing);
    $service = app(ChannelCenterService::class);
    $connection = m23ClearingConnection($service, $company, $clearing, 'Company Boundary');

    DB::table('integration_connections')->where('public_id', $connection)->update([
        'clearing_account_id' => $otherClearing->getKey(),
        'updated_at' => now(),
    ]);

    expect(fn () => $service->recordSettlementEvidence(
        (int) $company->getKey(),
        $connection,
        'M23-WRONG-COMPANY',
        'TRY',
        '100',
        '10',
        '2026-09-04T12:00:00+03:00',
        ['other_company' => (int) $otherCompany->getKey()],
    ))->toThrow(QueryException::class);
});

it('rejects a non-clearing account at settlement write time', function (): void {
    [$company, $mixed] = m23SettlementCompany('M23-SET-D', AccountType::Mixed);
    $service = app(ChannelCenterService::class);
    $connection = m23ClearingConnection($service, $company, $mixed, 'Wrong Type');

    expect(fn () => $service->recordSettlementEvidence(
        (int) $company->getKey(),
        $connection,
        'M23-WRONG-TYPE',
        'TRY',
        '100',
        '10',
        '2026-09-04T12:00:00+03:00',
        ['type' => 'mixed'],
    ))->toThrow(QueryException::class);
});

/** @return array{Company, Account} */
function m23SettlementCompany(string $code, AccountType $type): array
{
    $company = Company::query()->create(['code' => $code, 'name' => $code]);
    $account = Account::query()->create([
        'company_id' => $company->getKey(),
        'code' => $code.'-ACC',
        'type' => $type,
        'status' => AccountStatus::Active,
        'legal_name' => $code.' Account',
        'trade_name' => null,
        'tax_identity_type' => TaxIdentityType::None,
        'tax_number' => null,
        'tax_office' => null,
        'book_currency_code' => 'TRY',
        'due_days' => 0,
        'discount_rate' => '0.000000',
        'risk_limit' => '0.000000',
    ]);

    return [$company, $account];
}

function m23ClearingConnection(ChannelCenterService $service, Company $company, Account $account, string $name): string
{
    return $service->createConnection(
        companyId: (int) $company->getKey(),
        provider: 'woocommerce',
        name: $name,
        baseUrl: 'https://m23.example.test',
        credentials: ['consumer_key' => 'ck_m23', 'consumer_secret' => 'cs_m23'],
        webhookSecret: 'm23-settlement-webhook-secret',
        financialMode: 'clearing_account',
        clearingAccountId: (int) $account->getKey(),
    );
}

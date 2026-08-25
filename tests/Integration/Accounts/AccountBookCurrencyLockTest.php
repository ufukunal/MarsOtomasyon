<?php

use App\Foundation\Identity\SourceEffectIdentity;
use App\Modules\Accounts\Enums\AccountStatus;
use App\Modules\Accounts\Enums\AccountType;
use App\Modules\Accounts\Enums\TaxIdentityType;
use App\Modules\Accounts\Ledger\AccountTransactionPoster;
use App\Modules\Accounts\Ledger\PostAccountTransactionData;
use App\Modules\Accounts\Models\Account;
use App\Modules\Core\Authorization\AssignRoleToMembership;
use App\Modules\Core\Authorization\GrantPermissionToRole;
use App\Modules\Core\Enums\PermissionKey;
use App\Modules\Core\Enums\PostingPeriodStatus;
use App\Modules\Core\Enums\UserStatus;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\CompanyMembership;
use App\Modules\Core\Models\PostingPeriod;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;

uses(DatabaseMigrations::class);

beforeEach(function (): void {
    $this->withoutVite();
});

it('rejects book currency changes through account management after ledger activity', function (): void {
    $company = m26cCompany('M26-CURRENCY-APP');
    $actor = m26cActor($company);
    $account = m26cAccount($company, 'LOCKED-APP');
    m26cPeriod($company);
    m26cPostTransaction($company, $account, 'app-lock');

    $payload = m26cPayload($account);
    $payload['book_currency_code'] = 'USD';

    $this->actingAs($actor)
        ->withSession(['active_company_id' => $company->getKey()])
        ->put('/customers/'.$account->getKey(), $payload)
        ->assertSessionHasErrors('book_currency_code');

    expect($account->refresh()->book_currency_code)->toBe('TRY');
});

it('enforces the currency lock at PostgreSQL while allowing accounts without ledger activity to change', function (): void {
    $company = m26cCompany('M26-CURRENCY-DB');
    $locked = m26cAccount($company, 'LOCKED-DB');
    $unlocked = m26cAccount($company, 'UNLOCKED-DB');
    m26cPeriod($company);
    m26cPostTransaction($company, $locked, 'db-lock');

    expect(fn () => DB::table('accounts')
        ->where('id', $locked->getKey())
        ->update(['book_currency_code' => 'USD']))
        ->toThrow(QueryException::class);

    expect(DB::table('accounts')
        ->where('id', $unlocked->getKey())
        ->update(['book_currency_code' => 'USD']))
        ->toBe(1);

    expect($locked->refresh()->book_currency_code)->toBe('TRY')
        ->and($unlocked->refresh()->book_currency_code)->toBe('USD');
});

function m26cCompany(string $code): Company
{
    return Company::query()->create([
        'code' => $code,
        'name' => 'Company '.$code,
    ]);
}

function m26cActor(Company $company): User
{
    $user = User::query()->create([
        'name' => 'M2.6 Currency Manager',
        'email' => strtolower((string) $company->code).'@m26-currency.test',
        'password' => 'correct-password',
        'status' => UserStatus::Active,
    ]);
    $membership = CompanyMembership::query()->create([
        'company_id' => $company->getKey(),
        'user_id' => $user->getKey(),
        'is_active' => true,
        'joined_at' => now(),
    ]);
    $role = Role::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'm26-currency-manager',
        'name' => 'M2.6 Currency Manager',
        'is_active' => true,
    ]);

    app(GrantPermissionToRole::class)->handle($role, PermissionKey::AccountView);
    app(GrantPermissionToRole::class)->handle($role, PermissionKey::AccountManage);
    app(AssignRoleToMembership::class)->handle($membership, $role);

    return $user;
}

function m26cAccount(Company $company, string $code): Account
{
    return Account::query()->create([
        'company_id' => $company->getKey(),
        'code' => $code,
        'type' => AccountType::Customer,
        'status' => AccountStatus::Active,
        'legal_name' => 'Cari '.$code,
        'trade_name' => null,
        'tax_identity_type' => TaxIdentityType::None,
        'tax_number' => null,
        'tax_office' => null,
        'book_currency_code' => 'TRY',
        'due_days' => 0,
        'discount_rate' => '0',
        'risk_limit' => '0',
    ]);
}

function m26cPeriod(Company $company): PostingPeriod
{
    return PostingPeriod::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'M26-AUG',
        'name' => 'M2.6 August',
        'starts_on' => '2026-08-01',
        'ends_on' => '2026-08-31',
        'status' => PostingPeriodStatus::Open,
        'closed_at' => null,
    ]);
}

function m26cPostTransaction(Company $company, Account $account, string $sourceId): void
{
    DB::transaction(fn () => app(AccountTransactionPoster::class)->post(
        new PostAccountTransactionData(
            accountId: (int) $account->getKey(),
            postingDate: '2026-08-25',
            signedAmount: '10.000000',
            sourceEffect: new SourceEffectIdentity(
                companyId: (int) $company->getKey(),
                sourceType: 'test.account-currency',
                sourceId: $sourceId,
                effectType: 'account.debit',
            ),
        ),
    ));
}

/** @return array<string, int|string|null> */
function m26cPayload(Account $account): array
{
    return [
        'code' => (string) $account->code,
        'type' => $account->typeEnum()->value,
        'status' => $account->statusEnum()->value,
        'legal_name' => (string) $account->legal_name,
        'trade_name' => null,
        'tax_identity_type' => $account->taxIdentityTypeEnum()->value,
        'tax_number' => null,
        'tax_office' => null,
        'book_currency_code' => (string) $account->book_currency_code,
        'due_days' => (int) $account->due_days,
        'discount_rate' => (string) $account->discount_rate,
        'risk_limit' => (string) $account->risk_limit,
    ];
}

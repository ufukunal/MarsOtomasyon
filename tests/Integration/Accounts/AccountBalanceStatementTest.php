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
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;

uses(DatabaseMigrations::class);

beforeEach(function (): void {
    $this->withoutVite();
});

it('shows debtor creditor and zero balances from the immutable ledger on Cari list and detail', function (): void {
    $company = m27Company('M27-A');
    $viewer = m27Actor($company, [PermissionKey::AccountView], 'viewer-a');
    $debtor = m27Account($company, 'M27-DEBTOR');
    $creditor = m27Account($company, 'M27-CREDITOR');
    $zero = m27Account($company, 'M27-ZERO');
    m27Period($company);

    m27Post($company, $debtor, '2026-08-10', '1000', 'sales.invoice', 'invoice-debtor', 'account.debit', 'Satış faturası');
    m27Post($company, $debtor, '2026-08-15', '-250', 'treasury.collection', 'collection-debtor', 'account.credit', 'Kısmi tahsilat');
    m27Post($company, $creditor, '2026-08-12', '-100', 'treasury.collection', 'creditor-payment', 'account.credit', 'Avans tahsilat');

    $this->actingAs($viewer)
        ->withSession(['active_company_id' => $company->getKey()])
        ->get('/customers')
        ->assertOk()
        ->assertSee('750,00 TRY')
        ->assertSee('Borçlu')
        ->assertSee('100,00 TRY')
        ->assertSee('Alacaklı')
        ->assertSee('0,00 TRY')
        ->assertSee('Bakiye Yok');

    $this->actingAs($viewer)
        ->withSession(['active_company_id' => $company->getKey()])
        ->get('/customers/'.$debtor->getKey())
        ->assertOk()
        ->assertSee('750,00 TRY')
        ->assertSee('Borçlu')
        ->assertSee('Ekstre')
        ->assertDontSee('Düzenle');

    expect($zero->accountTransactions()->count())->toBe(0);
});

it('renders a filtered readonly statement with opening closing and running balances', function (): void {
    $company = m27Company('M27-B');
    $viewer = m27Actor($company, [PermissionKey::AccountView], 'viewer-b');
    $account = m27Account($company, 'M27-STMT');
    m27Period($company);

    m27Post($company, $account, '2026-08-10', '1000', 'sales.invoice', 'invoice-1', 'account.debit', 'Fatura 001');
    m27Post($company, $account, '2026-08-15', '-250', 'treasury.collection', 'collection-1', 'account.credit', 'Tahsilat 001');
    m27Post($company, $account, '2026-08-20', '-100', 'treasury.collection', 'collection-2', 'account.credit', 'Tahsilat 002');

    $this->actingAs($viewer)
        ->withSession(['active_company_id' => $company->getKey()])
        ->get('/customers/'.$account->getKey().'/statement')
        ->assertOk()
        ->assertSee('Satış Faturası')
        ->assertSee('Fatura 001')
        ->assertSee('Tahsilat')
        ->assertDontSee('sales.invoice')
        ->assertDontSee('effect_fingerprint')
        ->assertDontSee('idempotency');

    $this->actingAs($viewer)
        ->withSession(['active_company_id' => $company->getKey()])
        ->get('/customers/'.$account->getKey().'/statement?from=2026-08-15&to=2026-08-20')
        ->assertOk()
        ->assertSee('Cari Ekstresi')
        ->assertSee('Açılış Bakiyesi')
        ->assertSee('1.000,00 TRY')
        ->assertSee('Dönem Sonu Bakiyesi')
        ->assertSee('650,00 TRY')
        ->assertDontSee('Satış Faturası')
        ->assertDontSee('Fatura 001')
        ->assertSee('Tahsilat')
        ->assertSee('Tahsilat 001')
        ->assertSee('Tahsilat 002')
        ->assertSee('750,00 TRY')
        ->assertDontSee('sales.invoice')
        ->assertDontSee('effect_fingerprint')
        ->assertDontSee('idempotency');
});

it('keeps statement access company scoped and behind account view permission', function (): void {
    $companyA = m27Company('M27-C-A');
    $companyB = m27Company('M27-C-B');
    $viewerA = m27Actor($companyA, [PermissionKey::AccountView], 'viewer-c');
    $noPermission = m27Actor($companyA, [], 'none-c');
    $foreign = m27Account($companyB, 'M27-FOREIGN');

    $this->actingAs($viewerA)
        ->withSession(['active_company_id' => $companyA->getKey()])
        ->get('/customers/'.$foreign->getKey().'/statement')
        ->assertNotFound();

    $this->actingAs($noPermission)
        ->withSession(['active_company_id' => $companyA->getKey()])
        ->get('/customers')
        ->assertForbidden();

    $own = m27Account($companyA, 'M27-OWN');
    $this->actingAs($noPermission)
        ->withSession(['active_company_id' => $companyA->getKey()])
        ->get('/customers/'.$own->getKey().'/statement')
        ->assertForbidden();
});

it('rejects inverted statement date filters without reading ledger rows', function (): void {
    $company = m27Company('M27-D');
    $viewer = m27Actor($company, [PermissionKey::AccountView], 'viewer-d');
    $account = m27Account($company, 'M27-DATE');

    $this->actingAs($viewer)
        ->withSession(['active_company_id' => $company->getKey()])
        ->get('/customers/'.$account->getKey().'/statement?from=2026-08-20&to=2026-08-10')
        ->assertSessionHasErrors('to');
});

function m27Company(string $code): Company
{
    return Company::query()->create([
        'code' => $code,
        'name' => 'Company '.$code,
    ]);
}

/** @param list<PermissionKey> $permissions */
function m27Actor(Company $company, array $permissions, string $suffix): User
{
    $user = User::query()->create([
        'name' => 'M2.7 '.$suffix,
        'email' => strtolower((string) $company->code).'-'.$suffix.'@m27.test',
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
        'code' => 'm27-'.$suffix,
        'name' => 'M2.7 '.$suffix,
        'is_active' => true,
    ]);

    foreach ($permissions as $permission) {
        app(GrantPermissionToRole::class)->handle($role, $permission);
    }
    app(AssignRoleToMembership::class)->handle($membership, $role);

    return $user;
}

function m27Account(Company $company, string $code): Account
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

function m27Period(Company $company): PostingPeriod
{
    return PostingPeriod::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'M27-AUG',
        'name' => 'M2.7 August',
        'starts_on' => '2026-08-01',
        'ends_on' => '2026-08-31',
        'status' => PostingPeriodStatus::Open,
        'closed_at' => null,
    ]);
}

function m27Post(
    Company $company,
    Account $account,
    string $postingDate,
    string $signedAmount,
    string $sourceType,
    string $sourceId,
    string $effectType,
    string $memo,
): void {
    DB::transaction(fn () => app(AccountTransactionPoster::class)->post(
        new PostAccountTransactionData(
            accountId: (int) $account->getKey(),
            postingDate: $postingDate,
            signedAmount: $signedAmount,
            sourceEffect: new SourceEffectIdentity(
                companyId: (int) $company->getKey(),
                sourceType: $sourceType,
                sourceId: $sourceId,
                effectType: $effectType,
            ),
            memo: $memo,
        ),
    ));
}

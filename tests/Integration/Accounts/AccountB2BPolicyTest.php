<?php

use App\Modules\Accounts\Enums\AccountStatus;
use App\Modules\Accounts\Enums\AccountType;
use App\Modules\Accounts\Enums\TaxIdentityType;
use App\Modules\Accounts\Models\Account;
use App\Modules\Accounts\Models\AccountB2BPolicy;
use App\Modules\Core\Authorization\AssignRoleToMembership;
use App\Modules\Core\Authorization\GrantPermissionToRole;
use App\Modules\Core\Enums\AuditAction;
use App\Modules\Core\Enums\PermissionKey;
use App\Modules\Core\Enums\UserStatus;
use App\Modules\Core\Models\AuditEntry;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\CompanyMembership;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;

uses(DatabaseMigrations::class);

beforeEach(function (): void {
    $this->withoutVite();
});

it('updates account B2B policy without duplicating or mutating commercial risk and discount fields', function (): void {
    $company = m25Company('M25-A');
    $actor = m25Actor($company, [PermissionKey::AccountView, PermissionKey::AccountManage], 'manager');
    $account = m25Account($company, 'M25-001', '12.500000', '25000.000000');

    $this->actingAs($actor)
        ->withSession(['active_company_id' => $company->getKey()])
        ->get('/customers/'.$account->getKey().'/b2b/edit')
        ->assertOk()
        ->assertSee('B2B Erişim Politikası')
        ->assertSee('Cari İskontosu')
        ->assertSee('Risk Limiti');

    $this->actingAs($actor)
        ->withHeader('X-Correlation-ID', 'm25-b2b-001')
        ->withSession(['active_company_id' => $company->getKey()])
        ->put('/customers/'.$account->getKey().'/b2b', [
            'is_enabled' => '1',
            'allow_orders' => '1',
            'show_stock' => '1',
            'show_invoices' => '1',
            'show_statement' => '0',
            'allow_address_management' => '1',
        ])
        ->assertRedirect('/customers/'.$account->getKey());

    $policy = AccountB2BPolicy::query()->where('account_id', $account->getKey())->firstOrFail();
    expect($policy->company_id)->toBe($company->getKey())
        ->and($policy->is_enabled)->toBeTrue()
        ->and($policy->allow_orders)->toBeTrue()
        ->and($policy->show_stock)->toBeTrue()
        ->and($policy->show_invoices)->toBeTrue()
        ->and($policy->show_statement)->toBeFalse()
        ->and($policy->allow_address_management)->toBeTrue();

    $account->refresh();
    expect($account->discount_rate)->toBe('12.500000')
        ->and($account->risk_limit)->toBe('25000.000000');

    $audit = AuditEntry::query()->where('action', AuditAction::AccountB2BPolicyUpdated->value)->firstOrFail();
    expect($audit->correlation_id)->toBe('m25-b2b-001')
        ->and($audit->after_state['is_enabled'])->toBeTrue()
        ->and($audit->after_state['show_statement'])->toBeFalse();

    $this->actingAs($actor)
        ->withSession(['active_company_id' => $company->getKey()])
        ->get('/customers/'.$account->getKey())
        ->assertOk()
        ->assertSee('B2B / Bayi Erişimi')
        ->assertSee('Stok Görünürlüğü')
        ->assertSee('Ekstre Görünürlüğü');
});

it('keeps B2B policy management behind account manage permission', function (): void {
    $company = m25Company('M25-B');
    $viewer = m25Actor($company, [PermissionKey::AccountView], 'viewer');
    $account = m25Account($company, 'M25-002');

    $this->actingAs($viewer)
        ->withSession(['active_company_id' => $company->getKey()])
        ->get('/customers/'.$account->getKey())
        ->assertOk();

    $this->actingAs($viewer)
        ->withSession(['active_company_id' => $company->getKey()])
        ->get('/customers/'.$account->getKey().'/b2b/edit')
        ->assertForbidden();

    $this->actingAs($viewer)
        ->withSession(['active_company_id' => $company->getKey()])
        ->put('/customers/'.$account->getKey().'/b2b', m25PolicyPayload())
        ->assertForbidden();

    expect(AccountB2BPolicy::query()->where('account_id', $account->getKey())->exists())->toBeFalse();
});

it('rejects cross company B2B access and enforces company account integrity in PostgreSQL', function (): void {
    $companyA = m25Company('M25-C-A');
    $companyB = m25Company('M25-C-B');
    $actorA = m25Actor($companyA, [PermissionKey::AccountView, PermissionKey::AccountManage], 'manager-a');
    $accountB = m25Account($companyB, 'M25-B');

    $this->actingAs($actorA)
        ->withSession(['active_company_id' => $companyA->getKey()])
        ->get('/customers/'.$accountB->getKey().'/b2b/edit')
        ->assertNotFound();

    $this->actingAs($actorA)
        ->withSession(['active_company_id' => $companyA->getKey()])
        ->put('/customers/'.$accountB->getKey().'/b2b', m25PolicyPayload())
        ->assertNotFound();

    expect(fn () => DB::table('account_b2b_policies')->insert([
        'company_id' => $companyA->getKey(),
        'account_id' => $accountB->getKey(),
        'is_enabled' => true,
        'allow_orders' => true,
        'show_stock' => true,
        'show_invoices' => true,
        'show_statement' => true,
        'allow_address_management' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

function m25Company(string $code): Company
{
    return Company::query()->create([
        'code' => $code,
        'name' => 'Company '.$code,
    ]);
}

/** @param list<PermissionKey> $permissions */
function m25Actor(Company $company, array $permissions, string $suffix): User
{
    $user = User::query()->create([
        'name' => 'M2.5 '.$suffix,
        'email' => strtolower((string) $company->code).'-'.$suffix.'@m25.test',
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
        'code' => 'm25-'.$suffix,
        'name' => 'M2.5 '.$suffix,
        'is_active' => true,
    ]);

    foreach ($permissions as $permission) {
        app(GrantPermissionToRole::class)->handle($role, $permission);
    }
    app(AssignRoleToMembership::class)->handle($membership, $role);

    return $user;
}

function m25Account(Company $company, string $code, string $discount = '0', string $risk = '0'): Account
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
        'discount_rate' => $discount,
        'risk_limit' => $risk,
    ]);
}

/** @return array<string, string> */
function m25PolicyPayload(): array
{
    return [
        'is_enabled' => '1',
        'allow_orders' => '1',
        'show_stock' => '1',
        'show_invoices' => '1',
        'show_statement' => '1',
        'allow_address_management' => '1',
    ];
}

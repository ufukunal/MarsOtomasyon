<?php

use App\Foundation\Logging\SensitiveDataRedactor;
use App\Modules\Accounts\Enums\AccountStatus;
use App\Modules\Accounts\Enums\AccountType;
use App\Modules\Accounts\Enums\TaxIdentityType;
use App\Modules\Accounts\Models\Account;
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
use Illuminate\Foundation\Testing\DatabaseMigrations;

uses(DatabaseMigrations::class);

beforeEach(function (): void {
    $this->withoutVite();
});

it('allows an account manager to create view edit and deactivate a company account', function (): void {
    $company = m22Company('M22-A');
    $actor = m22Actor($company, [PermissionKey::AccountView, PermissionKey::AccountManage], 'manager');

    $this->actingAs($actor)
        ->withSession(['active_company_id' => $company->getKey()])
        ->get('/customers')
        ->assertOk()
        ->assertSee('Cariler')
        ->assertSee('Yeni Cari');

    $this->actingAs($actor)
        ->withHeader('X-Correlation-ID', 'm22-create-001')
        ->withSession(['active_company_id' => $company->getKey()])
        ->post('/customers', m22Payload())
        ->assertRedirect();

    $account = Account::query()->where('company_id', $company->getKey())->firstOrFail();
    expect($account->code)->toBe('M22-001')
        ->and($account->statusEnum())->toBe(AccountStatus::Active)
        ->and($account->tax_number)->toBe('10000000146');

    $this->actingAs($actor)
        ->withSession(['active_company_id' => $company->getKey()])
        ->get('/customers/'.$account->getKey())
        ->assertOk()
        ->assertSee('Mars M2.2 A.Ş.')
        ->assertDontSee('name="legal_name"', false);

    $this->actingAs($actor)
        ->withSession(['active_company_id' => $company->getKey()])
        ->get('/customers/'.$account->getKey().'/edit')
        ->assertOk()
        ->assertSee('name="legal_name"', false)
        ->assertSee('Cari Düzenle');

    $updatedPayload = m22Payload();
    $updatedPayload['status'] = AccountStatus::Inactive->value;
    $updatedPayload['legal_name'] = 'Mars M2.2 Güncel A.Ş.';

    $this->actingAs($actor)
        ->withHeader('X-Correlation-ID', 'm22-update-001')
        ->withSession(['active_company_id' => $company->getKey()])
        ->put('/customers/'.$account->getKey(), $updatedPayload)
        ->assertRedirect('/customers/'.$account->getKey());

    $account->refresh();
    expect($account->statusEnum())->toBe(AccountStatus::Inactive)
        ->and($account->legal_name)->toBe('Mars M2.2 Güncel A.Ş.');

    $audit = AuditEntry::query()->where('action', AuditAction::AccountUpdated->value)->firstOrFail();
    expect($audit->correlation_id)->toBe('m22-update-001')
        ->and($audit->before_state['status'])->toBe(AccountStatus::Active->value)
        ->and($audit->after_state['status'])->toBe(AccountStatus::Inactive->value)
        ->and($audit->after_state['tax_number'])->toBe(SensitiveDataRedactor::REDACTED);
});

it('keeps account viewing separate from account management', function (): void {
    $company = m22Company('M22-B');
    $viewer = m22Actor($company, [PermissionKey::AccountView], 'viewer');
    $account = m22Account($company, 'VIEW-1');

    $this->actingAs($viewer)
        ->withSession(['active_company_id' => $company->getKey()])
        ->get('/customers')
        ->assertOk()
        ->assertSee($account->legal_name)
        ->assertDontSee('Yeni Cari');

    $this->actingAs($viewer)
        ->withSession(['active_company_id' => $company->getKey()])
        ->get('/customers/'.$account->getKey())
        ->assertOk()
        ->assertDontSee('Düzenle');

    $this->actingAs($viewer)
        ->withSession(['active_company_id' => $company->getKey()])
        ->get('/customers/create')
        ->assertForbidden();

    $this->actingAs($viewer)
        ->withSession(['active_company_id' => $company->getKey()])
        ->get('/customers/'.$account->getKey().'/edit')
        ->assertForbidden();
});

it('denies account screens without account permissions', function (): void {
    $company = m22Company('M22-C');
    $actor = m22Actor($company, [], 'none');

    $this->actingAs($actor)
        ->withSession(['active_company_id' => $company->getKey()])
        ->get('/customers')
        ->assertForbidden();
});

it('does not expose or mutate another company account by route id', function (): void {
    $companyA = m22Company('M22-D-A');
    $companyB = m22Company('M22-D-B');
    $actorA = m22Actor($companyA, [PermissionKey::AccountView, PermissionKey::AccountManage], 'manager-a');
    $foreign = m22Account($companyB, 'FOREIGN-1');

    $this->actingAs($actorA)
        ->withSession(['active_company_id' => $companyA->getKey()])
        ->get('/customers/'.$foreign->getKey())
        ->assertNotFound();

    $this->actingAs($actorA)
        ->withSession(['active_company_id' => $companyA->getKey()])
        ->get('/customers/'.$foreign->getKey().'/edit')
        ->assertNotFound();

    $payload = m22Payload();
    $payload['status'] = AccountStatus::Inactive->value;

    $this->actingAs($actorA)
        ->withSession(['active_company_id' => $companyA->getKey()])
        ->put('/customers/'.$foreign->getKey(), $payload)
        ->assertNotFound();

    expect($foreign->refresh()->statusEnum())->toBe(AccountStatus::Active);
});

function m22Company(string $code): Company
{
    return Company::query()->create([
        'code' => $code,
        'name' => 'Company '.$code,
    ]);
}

/** @param list<PermissionKey> $permissions */
function m22Actor(Company $company, array $permissions, string $suffix): User
{
    $user = User::query()->create([
        'name' => 'M2.2 '.$suffix,
        'email' => strtolower((string) $company->code).'-'.$suffix.'@m22.test',
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
        'code' => 'm22-'.$suffix,
        'name' => 'M2.2 '.$suffix,
        'is_active' => true,
    ]);

    foreach ($permissions as $permission) {
        app(GrantPermissionToRole::class)->handle($role, $permission);
    }
    app(AssignRoleToMembership::class)->handle($membership, $role);

    return $user;
}

function m22Account(Company $company, string $code): Account
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

/** @return array<string, int|string|null> */
function m22Payload(): array
{
    return [
        'code' => 'm22-001',
        'type' => AccountType::Customer->value,
        'legal_name' => ' Mars M2.2 A.Ş. ',
        'trade_name' => 'Mars',
        'tax_identity_type' => TaxIdentityType::Tckn->value,
        'tax_number' => '10000000146',
        'tax_office' => 'Kadıköy',
        'book_currency_code' => 'TRY',
        'due_days' => 30,
        'discount_rate' => '5.000000',
        'risk_limit' => '250000.000000',
    ];
}

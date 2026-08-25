<?php

use App\Foundation\Logging\SensitiveDataRedactor;
use App\Modules\Accounts\Enums\AccountAddressType;
use App\Modules\Accounts\Enums\AccountContactKind;
use App\Modules\Accounts\Enums\AccountStatus;
use App\Modules\Accounts\Enums\AccountType;
use App\Modules\Accounts\Enums\TaxIdentityType;
use App\Modules\Accounts\Models\Account;
use App\Modules\Accounts\Models\AccountAuthorizedContact;
use App\Modules\Accounts\Models\AccountContact;
use App\Modules\Accounts\Models\AccountShippingPreference;
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

it('updates company scoped contacts authorized people addresses and manual shipping preferences atomically', function (): void {
    $company = m23Company('M23-A');
    $actor = m23Actor($company, [PermissionKey::AccountView, PermissionKey::AccountManage], 'manager');
    $account = m23Account($company, 'M23-001');

    $this->actingAs($actor)
        ->withSession(['active_company_id' => $company->getKey()])
        ->get('/customers/'.$account->getKey().'/profile/edit')
        ->assertOk()
        ->assertSee('Firma İletişim Kanalları')
        ->assertSee('Yetkililer')
        ->assertSee('Fatura / Sevk Adresleri')
        ->assertSee('Alternatif Firma Ekle');

    $this->actingAs($actor)
        ->withHeader('X-Correlation-ID', 'm23-profile-001')
        ->withSession(['active_company_id' => $company->getKey()])
        ->put('/customers/'.$account->getKey().'/profile', m23ProfilePayload())
        ->assertRedirect('/customers/'.$account->getKey());

    $phone = AccountContact::query()->where('account_id', $account->getKey())->where('kind', AccountContactKind::Phone->value)->firstOrFail();
    $email = AccountContact::query()->where('account_id', $account->getKey())->where('kind', AccountContactKind::Email->value)->firstOrFail();
    $primary = AccountAuthorizedContact::query()->where('account_id', $account->getKey())->where('is_primary', true)->firstOrFail();
    $shipping = AccountShippingPreference::query()->where('account_id', $account->getKey())->where('is_default', true)->firstOrFail();

    expect($phone->value)->toBe('+905551112233')
        ->and($phone->is_primary)->toBeTrue()
        ->and($email->value)->toBe('muhasebe@example.com')
        ->and($primary->name)->toBe('Ayşe Yetkili')
        ->and($primary->email)->toBe('ayse@example.com')
        ->and($account->addresses()->count())->toBe(2)
        ->and($shipping->company_name)->toBe('Mars Ambar')
        ->and($shipping->city)->toBe('İstanbul');

    $this->actingAs($actor)
        ->withSession(['active_company_id' => $company->getKey()])
        ->get('/customers/'.$account->getKey())
        ->assertOk()
        ->assertSee('+905551112233')
        ->assertSee('Ayşe Yetkili')
        ->assertSee('Fatura Merkezi')
        ->assertSee('Mars Ambar');

    $audit = AuditEntry::query()->where('action', AuditAction::AccountProfileUpdated->value)->firstOrFail();
    $serialized = json_encode($audit->after_state, JSON_THROW_ON_ERROR);

    expect($audit->correlation_id)->toBe('m23-profile-001')
        ->and($serialized)->not->toContain('+905551112233')
        ->and($serialized)->not->toContain('muhasebe@example.com')
        ->and($serialized)->not->toContain('ayse@example.com')
        ->and($serialized)->not->toContain('Organize Sanayi')
        ->and($serialized)->toContain(SensitiveDataRedactor::REDACTED);
});

it('enforces one primary authorized contact and keeps profile management permission separate', function (): void {
    $company = m23Company('M23-B');
    $viewer = m23Actor($company, [PermissionKey::AccountView], 'viewer');
    $manager = m23Actor($company, [PermissionKey::AccountView, PermissionKey::AccountManage], 'manager');
    $account = m23Account($company, 'M23-002');

    $this->actingAs($viewer)
        ->withSession(['active_company_id' => $company->getKey()])
        ->get('/customers/'.$account->getKey().'/profile/edit')
        ->assertForbidden();

    $payload = m23ProfilePayload();
    $payload['authorized_contacts'][1]['is_primary'] = '1';

    $this->actingAs($manager)
        ->withSession(['active_company_id' => $company->getKey()])
        ->from('/customers/'.$account->getKey().'/profile/edit')
        ->put('/customers/'.$account->getKey().'/profile', $payload)
        ->assertRedirect('/customers/'.$account->getKey().'/profile/edit')
        ->assertSessionHasErrors('authorized_contacts');

    expect(AccountAuthorizedContact::query()->where('account_id', $account->getKey())->count())->toBe(0);
});

it('rejects cross company child ids and enforces company account integrity at PostgreSQL boundary', function (): void {
    $companyA = m23Company('M23-C-A');
    $companyB = m23Company('M23-C-B');
    $actorA = m23Actor($companyA, [PermissionKey::AccountView, PermissionKey::AccountManage], 'manager-a');
    $accountA = m23Account($companyA, 'M23-A');
    $accountB = m23Account($companyB, 'M23-B');

    $foreignContact = AccountContact::query()->create([
        'company_id' => $companyB->getKey(),
        'account_id' => $accountB->getKey(),
        'kind' => AccountContactKind::Phone,
        'label' => 'Foreign',
        'value' => '+905550000001',
        'normalized_value' => '+905550000001',
        'is_primary' => true,
    ]);

    $this->actingAs($actorA)
        ->withSession(['active_company_id' => $companyA->getKey()])
        ->from('/customers/'.$accountA->getKey().'/profile/edit')
        ->put('/customers/'.$accountA->getKey().'/profile', [
            'contacts' => [[
                'id' => $foreignContact->getKey(),
                'kind' => AccountContactKind::Phone->value,
                'label' => 'Injected',
                'value' => '+905550000002',
                'is_primary' => '1',
            ]],
            'authorized_contacts' => [],
            'addresses' => [],
            'shipping_preferences' => [],
        ])
        ->assertRedirect('/customers/'.$accountA->getKey().'/profile/edit')
        ->assertSessionHasErrors('contacts.0.id');

    expect($foreignContact->refresh()->value)->toBe('+905550000001');

    expect(fn () => DB::table('account_contacts')->insert([
        'company_id' => $companyA->getKey(),
        'account_id' => $accountB->getKey(),
        'kind' => AccountContactKind::Email->value,
        'label' => 'Invalid tenant link',
        'value' => 'cross@example.com',
        'normalized_value' => 'cross@example.com',
        'is_primary' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

function m23Company(string $code): Company
{
    return Company::query()->create([
        'code' => $code,
        'name' => 'Company '.$code,
    ]);
}

/** @param list<PermissionKey> $permissions */
function m23Actor(Company $company, array $permissions, string $suffix): User
{
    $user = User::query()->create([
        'name' => 'M2.3 '.$suffix,
        'email' => strtolower((string) $company->code).'-'.$suffix.'@m23.test',
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
        'code' => 'm23-'.$suffix,
        'name' => 'M2.3 '.$suffix,
        'is_active' => true,
    ]);

    foreach ($permissions as $permission) {
        app(GrantPermissionToRole::class)->handle($role, $permission);
    }
    app(AssignRoleToMembership::class)->handle($membership, $role);

    return $user;
}

function m23Account(Company $company, string $code): Account
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

/** @return array<string, mixed> */
function m23ProfilePayload(): array
{
    return [
        'contacts' => [
            [
                'kind' => AccountContactKind::Phone->value,
                'label' => 'Ofis',
                'value' => '+90 (555) 111 22 33',
                'is_primary' => '1',
            ],
            [
                'kind' => AccountContactKind::Email->value,
                'label' => 'Muhasebe',
                'value' => ' MUHASEBE@EXAMPLE.COM ',
                'is_primary' => '1',
            ],
        ],
        'authorized_contacts' => [
            [
                'name' => ' Ayşe Yetkili ',
                'title' => 'Satınalma',
                'phone' => '+90 555 333 44 55',
                'email' => ' AYSE@EXAMPLE.COM ',
                'is_primary' => '1',
                'note' => 'Ana yetkili',
            ],
            [
                'name' => 'Mehmet Yedek',
                'title' => 'Muhasebe',
                'phone' => '+90 555 444 55 66',
                'email' => null,
                'is_primary' => '0',
                'note' => null,
            ],
        ],
        'addresses' => [
            [
                'type' => AccountAddressType::Billing->value,
                'label' => 'Fatura Merkezi',
                'recipient_name' => 'Mars A.Ş.',
                'line1' => 'Organize Sanayi 1. Cadde No:1',
                'line2' => null,
                'district' => 'Başakşehir',
                'city' => 'İstanbul',
                'postal_code' => '34490',
                'country_code' => 'tr',
                'is_default' => '1',
            ],
            [
                'type' => AccountAddressType::Shipping->value,
                'label' => 'Ana Depo',
                'recipient_name' => 'Mars Depo',
                'line1' => 'Depo Sokak No:5',
                'line2' => null,
                'district' => 'Gebze',
                'city' => 'Kocaeli',
                'postal_code' => '41400',
                'country_code' => 'TR',
                'is_default' => '1',
            ],
        ],
        'shipping_preferences' => [
            [
                'company_name' => 'Mars Ambar',
                'city' => 'İstanbul',
                'branch' => 'İkitelli',
                'contact_name' => 'Ali Ambar',
                'phone' => '+90 555 777 88 99',
                'preference' => 'Öncelikli',
                'address' => 'İkitelli OSB',
                'note' => 'Kırılabilir ürünlerde dikkat',
                'is_default' => '1',
            ],
            [
                'company_name' => 'Yedek Nakliye',
                'city' => 'İstanbul',
                'branch' => null,
                'contact_name' => null,
                'phone' => null,
                'preference' => 'Alternatif',
                'address' => null,
                'note' => null,
                'is_default' => '0',
            ],
        ],
    ];
}

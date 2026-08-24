<?php

use App\Modules\Core\Authorization\AssignRoleToMembership;
use App\Modules\Core\Authorization\GrantPermissionToRole;
use App\Modules\Core\Enums\PermissionKey;
use App\Modules\Core\Enums\PostingPeriodStatus;
use App\Modules\Core\Enums\UserStatus;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\CompanyMembership;
use App\Modules\Core\Models\ExchangeRate;
use App\Modules\Core\Models\PostingPeriod;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\Tax;
use App\Modules\Core\Models\TaxZeroReason;
use App\Modules\Core\Models\User;
use App\Modules\Core\Posting\PostingPeriodGuard;
use DomainException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;

uses(DatabaseMigrations::class);

beforeEach(function (): void {
    $this->withoutVite();
});

it('ships the core active currency catalog and rejects an unknown company base currency', function (): void {
    $company = m16Company('M16-A');
    $actor = m16Actor($company, [PermissionKey::SettingsView, PermissionKey::SettingsManage], 'manager');

    expect(DB::table('currencies')->whereIn('code', ['TRY', 'USD', 'EUR', 'GBP'])->count())->toBe(4);

    $this->actingAs($actor)
        ->withSession(['active_company_id' => $company->getKey()])
        ->from('/settings/company/edit')
        ->put('/settings/company', [
            'base_currency_code' => 'ZZZ',
            'timezone' => 'Europe/Istanbul',
        ])
        ->assertRedirect('/settings/company/edit')
        ->assertSessionHasErrors('base_currency_code');
});

it('creates a company scoped tax with normalized code and exact decimal rate', function (): void {
    $company = m16Company('M16-B');
    $actor = m16Actor($company, [PermissionKey::SettingsView, PermissionKey::SettingsManage], 'manager');

    $this->actingAs($actor)
        ->withSession(['active_company_id' => $company->getKey()])
        ->post('/settings/taxes', [
            'code' => 'kdv20',
            'name' => 'KDV %20',
            'rate' => '20.000000',
            'is_active' => '1',
        ])
        ->assertRedirect();

    $tax = Tax::query()->firstOrFail();
    expect($tax->company_id)->toBe($company->getKey())
        ->and($tax->code)->toBe('KDV20')
        ->and($tax->rate)->toBe('20.000000')
        ->and($tax->is_active)->toBeTrue();
});

it('rejects tax rates above one hundred percent', function (): void {
    $company = m16Company('M16-C');
    $actor = m16Actor($company, [PermissionKey::SettingsManage], 'manager');

    $this->actingAs($actor)
        ->withSession(['active_company_id' => $company->getKey()])
        ->from('/settings/taxes/create')
        ->post('/settings/taxes', [
            'code' => 'BAD',
            'name' => 'Bad Rate',
            'rate' => '100.000001',
            'is_active' => '1',
        ])
        ->assertRedirect('/settings/taxes/create')
        ->assertSessionHasErrors('rate');

    expect(Tax::query()->count())->toBe(0);
});

it('does not expose another company tax by route id', function (): void {
    $companyA = m16Company('M16-D-A');
    $companyB = m16Company('M16-D-B');
    $actor = m16Actor($companyA, [PermissionKey::SettingsView], 'viewer');
    $tax = Tax::query()->create([
        'company_id' => $companyB->getKey(),
        'code' => 'KDV20',
        'name' => 'Foreign Tax',
        'rate' => '20.000000',
        'is_active' => true,
    ]);

    $this->actingAs($actor)
        ->withSession(['active_company_id' => $companyA->getKey()])
        ->get('/settings/taxes/'.$tax->getKey())
        ->assertNotFound();
});

it('creates a company scoped tax zero reason', function (): void {
    $company = m16Company('M16-E');
    $actor = m16Actor($company, [PermissionKey::SettingsManage], 'manager');

    $this->actingAs($actor)
        ->withSession(['active_company_id' => $company->getKey()])
        ->post('/settings/tax-zero-reasons', [
            'code' => 'ISTISNA',
            'name' => 'KDV istisnası',
            'is_active' => '1',
        ])
        ->assertRedirect();

    $reason = TaxZeroReason::query()->firstOrFail();
    expect($reason->company_id)->toBe($company->getKey())
        ->and($reason->code)->toBe('ISTISNA');
});

it('creates a precise manual exchange rate and keeps its identity immutable on edit', function (): void {
    $company = m16Company('M16-F');
    $actor = m16Actor($company, [PermissionKey::SettingsView, PermissionKey::SettingsManage], 'manager');

    $this->actingAs($actor)
        ->withSession(['active_company_id' => $company->getKey()])
        ->post('/settings/exchange-rates', [
            'rate_date' => '2026-08-24',
            'from_currency_code' => 'USD',
            'to_currency_code' => 'TRY',
            'rate' => '41.1234567890',
            'source' => 'manual',
        ])
        ->assertRedirect();

    $rate = ExchangeRate::query()->firstOrFail();
    expect($rate->rate)->toBe('41.1234567890')
        ->and($rate->from_currency_code)->toBe('USD')
        ->and($rate->to_currency_code)->toBe('TRY');

    $this->actingAs($actor)
        ->withSession(['active_company_id' => $company->getKey()])
        ->put('/settings/exchange-rates/'.$rate->getKey(), [
            'rate_date' => '2025-01-01',
            'from_currency_code' => 'EUR',
            'to_currency_code' => 'TRY',
            'rate' => '42.0000000001',
            'source' => 'manual-correction',
        ])
        ->assertRedirect();

    $rate->refresh();
    expect($rate->rate_date?->format('Y-m-d'))->toBe('2026-08-24')
        ->and($rate->from_currency_code)->toBe('USD')
        ->and($rate->to_currency_code)->toBe('TRY')
        ->and($rate->rate)->toBe('42.0000000001');
});

it('rejects duplicate or same-currency exchange rate identities', function (): void {
    $company = m16Company('M16-G');
    $actor = m16Actor($company, [PermissionKey::SettingsManage], 'manager');

    ExchangeRate::query()->create([
        'company_id' => $company->getKey(),
        'rate_date' => '2026-08-24',
        'from_currency_code' => 'EUR',
        'to_currency_code' => 'TRY',
        'rate' => '48.1000000000',
        'source' => 'manual',
    ]);

    $this->actingAs($actor)
        ->withSession(['active_company_id' => $company->getKey()])
        ->from('/settings/exchange-rates/create')
        ->post('/settings/exchange-rates', [
            'rate_date' => '2026-08-24',
            'from_currency_code' => 'EUR',
            'to_currency_code' => 'TRY',
            'rate' => '48.2000000000',
            'source' => 'manual',
        ])
        ->assertSessionHasErrors('rate_date');

    $this->actingAs($actor)
        ->withSession(['active_company_id' => $company->getKey()])
        ->from('/settings/exchange-rates/create')
        ->post('/settings/exchange-rates', [
            'rate_date' => '2026-08-25',
            'from_currency_code' => 'USD',
            'to_currency_code' => 'USD',
            'rate' => '1.0000000000',
            'source' => 'manual',
        ])
        ->assertSessionHasErrors('to_currency_code');
});

it('prevents overlapping posting periods at PostgreSQL level and closes periods irreversibly in normal UI', function (): void {
    $company = m16Company('M16-H');
    $actor = m16Actor($company, [PermissionKey::SettingsView, PermissionKey::SettingsManage], 'manager');

    $this->actingAs($actor)
        ->withSession(['active_company_id' => $company->getKey()])
        ->post('/settings/posting-periods', [
            'code' => '2026-01',
            'name' => 'Ocak 2026',
            'starts_on' => '2026-01-01',
            'ends_on' => '2026-01-31',
        ])
        ->assertRedirect();

    $period = PostingPeriod::query()->firstOrFail();

    $this->actingAs($actor)
        ->withSession(['active_company_id' => $company->getKey()])
        ->from('/settings/posting-periods/create')
        ->post('/settings/posting-periods', [
            'code' => '2026-OVERLAP',
            'name' => 'Çakışan dönem',
            'starts_on' => '2026-01-15',
            'ends_on' => '2026-02-15',
        ])
        ->assertSessionHasErrors('starts_on');

    $this->actingAs($actor)
        ->withSession(['active_company_id' => $company->getKey()])
        ->post('/settings/posting-periods/'.$period->getKey().'/close')
        ->assertRedirect();

    $period->refresh();
    expect($period->status)->toBe(PostingPeriodStatus::Closed)
        ->and($period->closed_at)->not->toBeNull();

    $this->actingAs($actor)
        ->withSession(['active_company_id' => $company->getKey()])
        ->get('/settings/posting-periods/'.$period->getKey().'/edit')
        ->assertStatus(409);
});

it('requires period validation to run inside the same business transaction as posting', function (): void {
    $company = m16Company('M16-I');
    $guard = app(PostingPeriodGuard::class);

    expect(fn () => $guard->assertOpen((int) $company->getKey(), '2026-08-24'))
        ->toThrow(DomainException::class, 'Dönem kontrolü business transaction içinde çalışmalıdır.');
});

it('allows posting only inside an open company period', function (): void {
    $company = m16Company('M16-J');
    $guard = app(PostingPeriodGuard::class);

    PostingPeriod::query()->create([
        'company_id' => $company->getKey(),
        'code' => '2026-Q3',
        'name' => '2026 Q3',
        'starts_on' => '2026-07-01',
        'ends_on' => '2026-09-30',
        'status' => PostingPeriodStatus::Open,
    ]);

    $period = DB::transaction(fn () => $guard->assertOpen((int) $company->getKey(), '2026-08-24'));
    expect($period->code)->toBe('2026-Q3');

    expect(fn () => DB::transaction(fn () => $guard->assertOpen((int) $company->getKey(), '2026-10-01')))
        ->toThrow(DomainException::class, 'İşlem tarihi için muhasebe dönemi bulunamadı.');
});

it('rejects posting inside a closed period', function (): void {
    $company = m16Company('M16-K');
    $guard = app(PostingPeriodGuard::class);

    PostingPeriod::query()->create([
        'company_id' => $company->getKey(),
        'code' => '2026-07',
        'name' => 'Temmuz 2026',
        'starts_on' => '2026-07-01',
        'ends_on' => '2026-07-31',
        'status' => PostingPeriodStatus::Closed,
        'closed_at' => now(),
    ]);

    expect(fn () => DB::transaction(fn () => $guard->assertOpen((int) $company->getKey(), '2026-07-15')))
        ->toThrow(DomainException::class, 'İşlem tarihi kapalı bir muhasebe döneminde.');
});

function m16Company(string $code): Company
{
    return Company::query()->create([
        'code' => $code,
        'name' => 'Company '.$code,
    ]);
}

function m16User(string $email): User
{
    return User::query()->create([
        'name' => 'M1.6 User',
        'email' => $email,
        'password' => 'correct-password',
        'status' => UserStatus::Active,
    ]);
}

/** @param list<PermissionKey> $permissions */
function m16Actor(Company $company, array $permissions, string $suffix): User
{
    $user = m16User(strtolower((string) $company->code).'-'.$suffix.'@m16.test');
    $membership = CompanyMembership::query()->create([
        'company_id' => $company->getKey(),
        'user_id' => $user->getKey(),
        'is_active' => true,
        'joined_at' => now(),
    ]);
    $role = Role::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'm16-'.$suffix,
        'name' => 'M1.6 '.$suffix,
        'is_active' => true,
    ]);

    foreach ($permissions as $permission) {
        app(GrantPermissionToRole::class)->handle($role, $permission);
    }

    app(AssignRoleToMembership::class)->handle($membership, $role);

    return $user;
}

<?php

use App\Foundation\Clock\Clock;
use App\Modules\Core\Authorization\AssignRoleToMembership;
use App\Modules\Core\Authorization\GrantPermissionToRole;
use App\Modules\Core\Enums\AuditAction;
use App\Modules\Core\Enums\PermissionKey;
use App\Modules\Core\Enums\PostingPeriodStatus;
use App\Modules\Core\Enums\UserStatus;
use App\Modules\Core\Models\AuditEntry;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\CompanyMembership;
use App\Modules\Core\Models\ExchangeRate;
use App\Modules\Core\Models\PostingPeriod;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\Tax;
use App\Modules\Core\Models\TaxZeroReason;
use App\Modules\Core\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\Support\FrozenClock;

uses(DatabaseMigrations::class);

beforeEach(function (): void {
    $this->withoutVite();
});

it('audits tax create and update with before and after snapshots', function (): void {
    $company = financeAuditCompany('FA-TAX');
    $actor = financeAuditActor($company, 'tax-manager');

    $this->actingAs($actor)
        ->withHeader('X-Correlation-ID', 'finance-tax-create')
        ->withSession(['active_company_id' => $company->getKey()])
        ->post('/settings/taxes', [
            'code' => 'KDV20',
            'name' => 'KDV %20',
            'rate' => '20.000000',
            'is_active' => '1',
        ])
        ->assertRedirect();

    $tax = Tax::query()->firstOrFail();

    $this->actingAs($actor)
        ->withHeader('X-Correlation-ID', 'finance-tax-update')
        ->withSession(['active_company_id' => $company->getKey()])
        ->put('/settings/taxes/'.$tax->getKey(), [
            'code' => 'KDV18',
            'name' => 'KDV %18',
            'rate' => '18.000000',
            'is_active' => '1',
        ])
        ->assertRedirect();

    $created = AuditEntry::query()->where('action', AuditAction::TaxCreated->value)->firstOrFail();
    $updated = AuditEntry::query()->where('action', AuditAction::TaxUpdated->value)->firstOrFail();

    expect($created->correlation_id)->toBe('finance-tax-create')
        ->and($created->before_state)->toBeNull()
        ->and($created->after_state['code'])->toBe('KDV20')
        ->and($created->after_state['rate'])->toBe('20.000000')
        ->and($updated->correlation_id)->toBe('finance-tax-update')
        ->and($updated->before_state['code'])->toBe('KDV20')
        ->and($updated->after_state['code'])->toBe('KDV18')
        ->and($updated->after_state['rate'])->toBe('18.000000');
});

it('audits tax zero reason create and update', function (): void {
    $company = financeAuditCompany('FA-ZERO');
    $actor = financeAuditActor($company, 'zero-manager');

    $this->actingAs($actor)
        ->withHeader('X-Correlation-ID', 'finance-zero-create')
        ->withSession(['active_company_id' => $company->getKey()])
        ->post('/settings/tax-zero-reasons', [
            'code' => 'ISTISNA',
            'name' => 'KDV istisnası',
            'is_active' => '1',
        ])
        ->assertRedirect();

    $reason = TaxZeroReason::query()->firstOrFail();

    $this->actingAs($actor)
        ->withHeader('X-Correlation-ID', 'finance-zero-update')
        ->withSession(['active_company_id' => $company->getKey()])
        ->put('/settings/tax-zero-reasons/'.$reason->getKey(), [
            'code' => 'ISTISNA',
            'name' => 'KDV istisnası güncel',
            'is_active' => '0',
        ])
        ->assertRedirect();

    $created = AuditEntry::query()->where('action', AuditAction::TaxZeroReasonCreated->value)->firstOrFail();
    $updated = AuditEntry::query()->where('action', AuditAction::TaxZeroReasonUpdated->value)->firstOrFail();

    expect($created->after_state['name'])->toBe('KDV istisnası')
        ->and($updated->before_state['is_active'])->toBeTrue()
        ->and($updated->after_state['name'])->toBe('KDV istisnası güncel')
        ->and($updated->after_state['is_active'])->toBeFalse();
});

it('audits exchange rate create and value correction without changing rate identity', function (): void {
    $company = financeAuditCompany('FA-FX');
    $actor = financeAuditActor($company, 'fx-manager');

    $this->actingAs($actor)
        ->withHeader('X-Correlation-ID', 'finance-fx-create')
        ->withSession(['active_company_id' => $company->getKey()])
        ->post('/settings/exchange-rates', [
            'rate_date' => '2026-08-25',
            'from_currency_code' => 'USD',
            'to_currency_code' => 'TRY',
            'rate' => '41.1234567890',
            'source' => 'manual',
        ])
        ->assertRedirect();

    $rate = ExchangeRate::query()->firstOrFail();

    $this->actingAs($actor)
        ->withHeader('X-Correlation-ID', 'finance-fx-update')
        ->withSession(['active_company_id' => $company->getKey()])
        ->put('/settings/exchange-rates/'.$rate->getKey(), [
            'rate' => '41.2234567890',
            'source' => 'manual-correction',
        ])
        ->assertRedirect();

    $updated = AuditEntry::query()->where('action', AuditAction::ExchangeRateUpdated->value)->firstOrFail();

    expect($updated->before_state['rate_date'])->toBe('2026-08-25')
        ->and($updated->after_state['rate_date'])->toBe('2026-08-25')
        ->and($updated->before_state['from_currency_code'])->toBe('USD')
        ->and($updated->after_state['from_currency_code'])->toBe('USD')
        ->and($updated->before_state['rate'])->toBe('41.1234567890')
        ->and($updated->after_state['rate'])->toBe('41.2234567890');
});

it('audits posting period lifecycle and uses the injected business clock for closure', function (): void {
    $company = financeAuditCompany('FA-PERIOD');
    $actor = financeAuditActor($company, 'period-manager');
    $clock = new FrozenClock('2026-08-25T00:30:00+03:00');
    $this->app->instance(Clock::class, $clock);

    $this->actingAs($actor)
        ->withHeader('X-Correlation-ID', 'finance-period-create')
        ->withSession(['active_company_id' => $company->getKey()])
        ->post('/settings/posting-periods', [
            'code' => '2026-08',
            'name' => 'Ağustos 2026',
            'starts_on' => '2026-08-01',
            'ends_on' => '2026-08-31',
        ])
        ->assertRedirect();

    $period = PostingPeriod::query()->firstOrFail();

    $this->actingAs($actor)
        ->withHeader('X-Correlation-ID', 'finance-period-update')
        ->withSession(['active_company_id' => $company->getKey()])
        ->put('/settings/posting-periods/'.$period->getKey(), [
            'code' => '2026-08',
            'name' => 'Ağustos 2026 Güncel',
            'starts_on' => '2026-08-01',
            'ends_on' => '2026-08-31',
        ])
        ->assertRedirect();

    $this->actingAs($actor)
        ->withHeader('X-Correlation-ID', 'finance-period-close')
        ->withSession(['active_company_id' => $company->getKey()])
        ->post('/settings/posting-periods/'.$period->getKey().'/close')
        ->assertRedirect();

    $period->refresh();
    $closed = AuditEntry::query()->where('action', AuditAction::PostingPeriodClosed->value)->firstOrFail();

    expect($period->status)->toBe(PostingPeriodStatus::Closed)
        ->and($period->closed_at?->format(DATE_ATOM))->toBe('2026-08-24T21:30:00+00:00')
        ->and($closed->correlation_id)->toBe('finance-period-close')
        ->and($closed->before_state['status'])->toBe('open')
        ->and($closed->before_state['closed_at'])->toBeNull()
        ->and($closed->after_state['status'])->toBe('closed')
        ->and($closed->after_state['closed_at'])->toBe('2026-08-24T21:30:00+00:00')
        ->and($closed->occurred_at?->format(DATE_ATOM))->toBe('2026-08-24T21:30:00+00:00');
});

it('does not create an audit entry when finance validation rejects the mutation', function (): void {
    $company = financeAuditCompany('FA-INVALID');
    $actor = financeAuditActor($company, 'invalid-manager');

    $this->actingAs($actor)
        ->withHeader('X-Correlation-ID', 'finance-invalid-tax')
        ->withSession(['active_company_id' => $company->getKey()])
        ->from('/settings/taxes/create')
        ->post('/settings/taxes', [
            'code' => 'BAD',
            'name' => 'Geçersiz',
            'rate' => '100.000001',
            'is_active' => '1',
        ])
        ->assertRedirect('/settings/taxes/create')
        ->assertSessionHasErrors('rate');

    expect(Tax::query()->count())->toBe(0)
        ->and(AuditEntry::query()->count())->toBe(0);
});

function financeAuditCompany(string $code): Company
{
    return Company::query()->create([
        'code' => $code,
        'name' => 'Company '.$code,
    ]);
}

function financeAuditActor(Company $company, string $suffix): User
{
    $user = User::query()->create([
        'name' => 'Finance Audit User',
        'email' => strtolower((string) $company->code).'-'.$suffix.'@finance-audit.test',
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
        'code' => 'finance-audit-'.$suffix,
        'name' => 'Finance Audit '.$suffix,
        'is_active' => true,
    ]);

    foreach ([PermissionKey::SettingsView, PermissionKey::SettingsManage] as $permission) {
        app(GrantPermissionToRole::class)->handle($role, $permission);
    }

    app(AssignRoleToMembership::class)->handle($membership, $role);

    return $user;
}

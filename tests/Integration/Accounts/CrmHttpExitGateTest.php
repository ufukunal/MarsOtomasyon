<?php

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
use Illuminate\Support\Facades\DB;

uses(DatabaseMigrations::class);

beforeEach(function (): void {
    $this->withoutVite();
});

it('runs the CRM workspace lifecycle with audit and no financial side effects', function (): void {
    $company = m30HttpCompany('M30-HTTP-A');
    $manager = m30HttpActor($company, [PermissionKey::CrmView, PermissionKey::CrmManage], 'manager');
    $account = m30HttpAccount($company, 'CRM-001');
    $financialBefore = DB::table('account_transactions')->count();

    $this->actingAs($manager)
        ->withSession(['active_company_id' => $company->getKey()])
        ->get('/customers/crm')
        ->assertOk()
        ->assertSee('CRM / Fırsatlar')
        ->assertSee('Yeni Lead');

    $this->actingAs($manager)
        ->withHeader('X-Correlation-ID', 'm30-lead-create')
        ->withSession(['active_company_id' => $company->getKey()])
        ->post('/customers/crm/leads', [
            'name' => 'Potansiyel Müşteri',
            'company_name' => 'Mars CRM A.Ş.',
            'email' => 'lead@m30.test',
            'owner_user_id' => $manager->getKey(),
        ])
        ->assertRedirect(route('crm.index'));

    $leadId = (int) DB::table('crm_leads')->where('company_id', $company->getKey())->value('id');

    $this->actingAs($manager)
        ->withHeader('X-Correlation-ID', 'm30-opportunity-create')
        ->withSession(['active_company_id' => $company->getKey()])
        ->post('/customers/crm/opportunities', [
            'name' => 'İlk Fırsat',
            'lead_id' => $leadId,
            'owner_user_id' => $manager->getKey(),
            'expected_value' => '25000.000000',
            'currency_code' => 'TRY',
        ])
        ->assertRedirect(route('crm.index'));

    $opportunityId = (int) DB::table('crm_opportunities')->where('company_id', $company->getKey())->value('id');
    expect(DB::table('crm_opportunity_stage_history')->where('opportunity_id', $opportunityId)->count())->toBe(1);

    $this->actingAs($manager)
        ->withHeader('X-Correlation-ID', 'm30-stage-proposal')
        ->withSession(['active_company_id' => $company->getKey()])
        ->patch('/customers/crm/opportunities/'.$opportunityId.'/stage', ['stage' => 'proposal'])
        ->assertRedirect(route('crm.index'));

    expect(DB::table('crm_opportunity_stage_history')->where('opportunity_id', $opportunityId)->count())->toBe(2)
        ->and(DB::table('crm_opportunities')->where('id', $opportunityId)->value('stage'))->toBe('proposal');

    $this->actingAs($manager)
        ->withHeader('X-Correlation-ID', 'm30-activity-create')
        ->withSession(['active_company_id' => $company->getKey()])
        ->post('/customers/crm/activities', [
            'lead_id' => $leadId,
            'opportunity_id' => $opportunityId,
            'activity_type' => 'follow_up',
            'subject' => 'Müşteriyi ara',
            'owner_user_id' => $manager->getKey(),
            'due_at' => now()->addDay()->format('Y-m-d H:i:s'),
        ])
        ->assertRedirect(route('crm.index'));

    $this->actingAs($manager)
        ->withHeader('X-Correlation-ID', 'm30-lead-convert')
        ->withSession(['active_company_id' => $company->getKey()])
        ->post('/customers/crm/leads/'.$leadId.'/convert', ['account_id' => $account->getKey()])
        ->assertRedirect(route('crm.index'));

    $this->actingAs($manager)
        ->withHeader('X-Correlation-ID', 'm30-lead-convert-repeat')
        ->withSession(['active_company_id' => $company->getKey()])
        ->post('/customers/crm/leads/'.$leadId.'/convert', ['account_id' => $account->getKey()])
        ->assertRedirect(route('crm.index'));

    $this->actingAs($manager)
        ->withHeader('X-Correlation-ID', 'm30-stage-cancel')
        ->withSession(['active_company_id' => $company->getKey()])
        ->patch('/customers/crm/opportunities/'.$opportunityId.'/stage', ['stage' => 'cancelled'])
        ->assertRedirect(route('crm.index'));

    expect((int) DB::table('crm_leads')->where('id', $leadId)->value('converted_account_id'))->toBe((int) $account->getKey())
        ->and(DB::table('crm_opportunities')->where('id', $opportunityId)->value('status'))->toBe('closed')
        ->and(DB::table('account_transactions')->count())->toBe($financialBefore)
        ->and(AuditEntry::query()->where('action', AuditAction::CrmLeadCreated->value)->exists())->toBeTrue()
        ->and(AuditEntry::query()->where('action', AuditAction::CrmOpportunityStageChanged->value)->exists())->toBeTrue()
        ->and(AuditEntry::query()->where('action', AuditAction::CrmActivityCreated->value)->exists())->toBeTrue()
        ->and(AuditEntry::query()->where('action', AuditAction::CrmLeadConverted->value)->exists())->toBeTrue();
});

it('keeps CRM viewing separate from CRM management', function (): void {
    $company = m30HttpCompany('M30-HTTP-B');
    $viewer = m30HttpActor($company, [PermissionKey::CrmView], 'viewer');

    $this->actingAs($viewer)
        ->withSession(['active_company_id' => $company->getKey()])
        ->get('/customers/crm')
        ->assertOk()
        ->assertDontSee('Yeni Lead');

    $this->actingAs($viewer)
        ->withSession(['active_company_id' => $company->getKey()])
        ->post('/customers/crm/leads', ['name' => 'Denied'])
        ->assertForbidden();
});

it('denies CRM without permission and fails closed when its feature is disabled', function (): void {
    $company = m30HttpCompany('M30-HTTP-C');
    $none = m30HttpActor($company, [], 'none');
    $viewer = m30HttpActor($company, [PermissionKey::CrmView], 'feature-off');

    $this->actingAs($none)
        ->withSession(['active_company_id' => $company->getKey()])
        ->get('/customers/crm')
        ->assertForbidden();

    config(['mars.features.light_crm' => false]);

    $this->actingAs($viewer)
        ->withSession(['active_company_id' => $company->getKey()])
        ->get('/customers/crm')
        ->assertNotFound();
});

function m30HttpCompany(string $code): Company
{
    return Company::query()->create(['code' => $code, 'name' => 'Company '.$code]);
}

/** @param list<PermissionKey> $permissions */
function m30HttpActor(Company $company, array $permissions, string $suffix): User
{
    $user = User::query()->create([
        'name' => 'M30 '.$suffix,
        'email' => strtolower((string) $company->code).'-'.$suffix.'@m30.test',
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
        'code' => 'm30-'.$suffix,
        'name' => 'M30 '.$suffix,
        'is_active' => true,
    ]);

    foreach ($permissions as $permission) {
        app(GrantPermissionToRole::class)->handle($role, $permission);
    }
    app(AssignRoleToMembership::class)->handle($membership, $role);

    return $user;
}

function m30HttpAccount(Company $company, string $code): Account
{
    return Account::query()->create([
        'company_id' => $company->getKey(),
        'code' => $code,
        'type' => AccountType::Customer,
        'status' => AccountStatus::Active,
        'legal_name' => 'Cari '.$code,
        'trade_name' => 'Mars CRM',
        'tax_identity_type' => TaxIdentityType::None,
        'tax_number' => null,
        'tax_office' => null,
        'book_currency_code' => 'TRY',
        'due_days' => 0,
        'discount_rate' => '0',
        'risk_limit' => '0',
    ]);
}

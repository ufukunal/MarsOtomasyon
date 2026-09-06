<?php

use App\Modules\Accounts\Crm\CrmService;
use App\Modules\Accounts\Enums\AccountStatus;
use App\Modules\Accounts\Enums\AccountType;
use App\Modules\Accounts\Enums\TaxIdentityType;
use App\Modules\Accounts\Models\Account;
use App\Modules\Core\Enums\UserStatus;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\CompanyMembership;
use App\Modules\Core\Models\User;
use DomainException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;

uses(DatabaseMigrations::class);

it('blocks foreign owners and foreign commercial account links', function (): void {
    $companyA = Company::query()->create(['code' => 'M30-SCOPE-A', 'name' => 'M30 Scope A']);
    $companyB = Company::query()->create(['code' => 'M30-SCOPE-B', 'name' => 'M30 Scope B']);
    $foreignOwner = m30ScopeUser($companyB, 'foreign');
    $foreignAccount = m30ScopeAccount($companyB, 'FOREIGN');
    $service = new CrmService;

    expect(fn () => $service->createLead((int) $companyA->getKey(), [
        'name' => 'Foreign owner lead',
        'owner_user_id' => (int) $foreignOwner->getKey(),
    ]))->toThrow(DomainException::class, 'active user of the company');

    $leadId = $service->createLead((int) $companyA->getKey(), ['name' => 'Scoped lead']);

    expect(fn () => $service->createOpportunity((int) $companyA->getKey(), [
        'name' => 'Foreign account opportunity',
        'lead_id' => $leadId,
        'account_id' => (int) $foreignAccount->getKey(),
    ]))->toThrow(DomainException::class, 'Account not found for company');

    expect(DB::table('crm_opportunities')->where('company_id', $companyA->getKey())->count())->toBe(0);
});

it('rejects unknown stages and keeps cancelled opportunities financially inert', function (): void {
    $company = Company::query()->create(['code' => 'M30-SCOPE-C', 'name' => 'M30 Scope C']);
    $service = new CrmService;
    $financialBefore = DB::table('account_transactions')->count();
    $leadId = $service->createLead((int) $company->getKey(), ['name' => 'Stage guard lead']);
    $opportunityId = $service->createOpportunity((int) $company->getKey(), [
        'name' => 'Stage guard opportunity',
        'lead_id' => $leadId,
    ]);

    expect(fn () => $service->moveOpportunityStage((int) $company->getKey(), $opportunityId, 'magic-stage'))
        ->toThrow(DomainException::class, 'stage is invalid');

    $service->moveOpportunityStage((int) $company->getKey(), $opportunityId, 'cancelled');

    expect(DB::table('crm_opportunities')->where('id', $opportunityId)->value('status'))->toBe('closed')
        ->and(DB::table('account_transactions')->count())->toBe($financialBefore);
});

function m30ScopeUser(Company $company, string $suffix): User
{
    $user = User::query()->create([
        'name' => 'M30 Scope '.$suffix,
        'email' => 'm30-scope-'.$suffix.'@test.local',
        'password' => 'correct-password',
        'status' => UserStatus::Active,
    ]);
    CompanyMembership::query()->create([
        'company_id' => $company->getKey(),
        'user_id' => $user->getKey(),
        'is_active' => true,
        'joined_at' => now(),
    ]);

    return $user;
}

function m30ScopeAccount(Company $company, string $code): Account
{
    return Account::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'M30-'.$code,
        'type' => AccountType::Customer,
        'status' => AccountStatus::Active,
        'legal_name' => 'Account '.$code,
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

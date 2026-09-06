<?php

use App\Modules\Accounts\Crm\CrmService;
use App\Modules\Accounts\Enums\AccountStatus;
use App\Modules\Accounts\Enums\AccountType;
use App\Modules\Accounts\Enums\TaxIdentityType;
use App\Modules\Accounts\Models\Account;
use App\Modules\Core\Models\Company;
use DomainException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;

uses(DatabaseMigrations::class);

it('tracks lead opportunity stage and explicit account conversion without financial effects', function (): void {
    $company = Company::query()->create(['code' => 'M30', 'name' => 'M30 Company']);
    $accountA = m30Account($company, 'A');
    $accountB = m30Account($company, 'B');
    $service = new CrmService;
    $financialBefore = DB::table('account_transactions')->count();

    $leadId = $service->createLead((int) $company->getKey(), ['name' => 'Potential Customer', 'email' => 'lead@example.test']);
    $opportunityId = $service->createOpportunity((int) $company->getKey(), [
        'name' => 'First Opportunity',
        'lead_id' => $leadId,
        'expected_value' => '25000.000000',
        'currency_code' => 'TRY',
    ]);
    $service->moveOpportunityStage((int) $company->getKey(), $opportunityId, 'proposal');
    $activityId = $service->addActivity((int) $company->getKey(), $leadId, $opportunityId, 'follow_up', 'Call customer');

    expect($service->convertLeadToAccount((int) $company->getKey(), $leadId, (int) $accountA->getKey()))->toBe((int) $accountA->getKey())
        ->and($service->convertLeadToAccount((int) $company->getKey(), $leadId, (int) $accountA->getKey()))->toBe((int) $accountA->getKey())
        ->and((int) DB::table('crm_opportunities')->where('id', $opportunityId)->value('account_id'))->toBe((int) $accountA->getKey())
        ->and(DB::table('crm_opportunity_stage_history')->where('opportunity_id', $opportunityId)->count())->toBe(2)
        ->and($activityId)->toBeGreaterThan(0)
        ->and(DB::table('account_transactions')->count())->toBe($financialBefore);

    expect(fn () => $service->convertLeadToAccount((int) $company->getKey(), $leadId, (int) $accountB->getKey()))
        ->toThrow(DomainException::class, 'already converted');
});

function m30Account(Company $company, string $code): Account
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
        'discount_rate' => '0.000000',
        'risk_limit' => '0.000000',
    ]);
}

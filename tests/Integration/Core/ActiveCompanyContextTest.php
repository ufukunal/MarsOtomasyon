<?php

use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\CompanyMembership;
use App\Modules\Core\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Route::middleware(['web', 'auth', 'company.context'])
        ->get('/__tests/company-context', function (ActiveCompanyContext $context) {
            return response()->json([
                'company_id' => $context->requireCompany()->getKey(),
            ]);
        });
});

it('resolves the active company from authenticated membership and ignores forged request company ids', function (): void {
    $companyA = Company::query()->create([
        'code' => 'NOYA',
        'name' => 'Noya Aydınlatma',
    ]);
    $companyB = Company::query()->create([
        'code' => 'OTHER',
        'name' => 'Other Company',
    ]);
    $user = User::query()->create([
        'name' => 'Mars User',
        'email' => 'mars@example.test',
        'password' => 'secret-password',
    ]);

    CompanyMembership::query()->create([
        'company_id' => $companyA->getKey(),
        'user_id' => $user->getKey(),
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->withSession(['active_company_id' => $companyA->getKey()])
        ->getJson('/__tests/company-context?company_id='.$companyB->getKey())
        ->assertOk()
        ->assertJsonPath('company_id', $companyA->getKey());
});

it('rejects a session company that is outside the authenticated users memberships', function (): void {
    $allowedCompany = Company::query()->create([
        'code' => 'ALLOWED',
        'name' => 'Allowed Company',
    ]);
    $forbiddenCompany = Company::query()->create([
        'code' => 'FORBIDDEN',
        'name' => 'Forbidden Company',
    ]);
    $user = User::query()->create([
        'name' => 'Mars User',
        'email' => 'security@example.test',
        'password' => 'secret-password',
    ]);

    CompanyMembership::query()->create([
        'company_id' => $allowedCompany->getKey(),
        'user_id' => $user->getKey(),
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->withSession(['active_company_id' => $forbiddenCompany->getKey()])
        ->getJson('/__tests/company-context')
        ->assertForbidden();
});

it('auto-selects the only active company membership', function (): void {
    $company = Company::query()->create([
        'code' => 'ONLY',
        'name' => 'Only Company',
    ]);
    $user = User::query()->create([
        'name' => 'Single Company User',
        'email' => 'single@example.test',
        'password' => 'secret-password',
    ]);

    CompanyMembership::query()->create([
        'company_id' => $company->getKey(),
        'user_id' => $user->getKey(),
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->getJson('/__tests/company-context')
        ->assertOk()
        ->assertJsonPath('company_id', $company->getKey())
        ->assertSessionHas('active_company_id', $company->getKey());
});

it('requires an explicit company selection when multiple active memberships exist', function (): void {
    $companyA = Company::query()->create([
        'code' => 'MULTI-A',
        'name' => 'Multi A',
    ]);
    $companyB = Company::query()->create([
        'code' => 'MULTI-B',
        'name' => 'Multi B',
    ]);
    $user = User::query()->create([
        'name' => 'Multi Company User',
        'email' => 'multi@example.test',
        'password' => 'secret-password',
    ]);

    foreach ([$companyA, $companyB] as $company) {
        CompanyMembership::query()->create([
            'company_id' => $company->getKey(),
            'user_id' => $user->getKey(),
            'is_active' => true,
        ]);
    }

    $this->actingAs($user)
        ->getJson('/__tests/company-context')
        ->assertStatus(409);
});

it('enforces case-insensitive company codes at the PostgreSQL boundary', function (): void {
    Company::query()->create([
        'code' => 'MARS',
        'name' => 'Mars One',
    ]);

    expect(fn () => Company::query()->create([
        'code' => 'mars',
        'name' => 'Mars Two',
    ]))->toThrow(QueryException::class);
});

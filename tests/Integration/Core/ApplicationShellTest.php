<?php

use App\Foundation\Features\FeatureRegistry;
use App\Modules\Core\Enums\CompanyStatus;
use App\Modules\Core\Enums\UserStatus;
use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\CompanyMembership;
use App\Modules\Core\Models\User;
use App\Modules\Core\Shell\AppNavigation;
use Illuminate\Foundation\Testing\DatabaseMigrations;

uses(DatabaseMigrations::class);

beforeEach(function (): void {
    $this->withoutVite();
});

it('redirects unauthenticated root requests to login', function (): void {
    $this->get('/')->assertRedirect('/login');
});

it('enters the workspace through login and the company context gateway', function (): void {
    $user = shellUser('login-workspace');
    $company = shellCompany('SHELL-LOGIN');
    shellMembership($user, $company);
    shellBranch($company, 'MAIN');

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'correct-password',
    ])->assertRedirect('/');

    $this->get('/')
        ->assertRedirect('/workspace')
        ->assertSessionHas('active_company_id', $company->getKey());

    $this->get('/workspace')
        ->assertOk()
        ->assertSee($company->name)
        ->assertSee('Ana Sayfa');
});

it('auto selects the only active company before entering workspace', function (): void {
    $user = shellUser('single-company');
    $company = shellCompany('SHELL-A');
    shellMembership($user, $company);

    $this->actingAs($user)
        ->get('/')
        ->assertRedirect('/workspace')
        ->assertSessionHas('active_company_id', $company->getKey());
});

it('requires an explicit company choice when multiple active memberships exist', function (): void {
    $user = shellUser('multi-company');
    $companyA = shellCompany('SHELL-B-A');
    $companyB = shellCompany('SHELL-B-B');
    shellMembership($user, $companyA);
    shellMembership($user, $companyB);

    $this->actingAs($user)
        ->get('/')
        ->assertRedirect('/context/companies');

    $this->actingAs($user)
        ->get('/context/companies')
        ->assertOk()
        ->assertSee($companyA->name)
        ->assertSee($companyB->name);
});

it('excludes suspended companies from selectable application context', function (): void {
    $user = shellUser('suspended-company');
    $active = shellCompany('SHELL-C-A');
    $suspended = shellCompany('SHELL-C-B', CompanyStatus::Suspended);
    shellMembership($user, $active);
    shellMembership($user, $suspended);

    $this->actingAs($user)
        ->get('/')
        ->assertRedirect('/workspace')
        ->assertSessionHas('active_company_id', $active->getKey());
});

it('clears branch context when switching company', function (): void {
    $user = shellUser('switch-company');
    $companyA = shellCompany('SHELL-D-A');
    $companyB = shellCompany('SHELL-D-B');
    shellMembership($user, $companyA);
    shellMembership($user, $companyB);
    $branchA = shellBranch($companyA, 'A1');

    $this->actingAs($user)
        ->withSession([
            'active_company_id' => $companyA->getKey(),
            'active_branch_id' => $branchA->getKey(),
        ])
        ->post('/context/companies/'.$companyB->getKey())
        ->assertRedirect('/workspace')
        ->assertSessionHas('active_company_id', $companyB->getKey())
        ->assertSessionMissing('active_branch_id');
});

it('auto selects the only active branch and renders only enabled navigation', function (): void {
    $user = shellUser('single-branch');
    $company = shellCompany('SHELL-E');
    shellMembership($user, $company);
    $branch = shellBranch($company, 'MAIN');

    $this->actingAs($user)
        ->withSession(['active_company_id' => $company->getKey()])
        ->get('/workspace')
        ->assertOk()
        ->assertSessionHas('active_branch_id', $branch->getKey())
        ->assertSee('Ana Sayfa')
        ->assertSee('Ayarlar')
        ->assertDontSee('Cariler')
        ->assertDontSee('Satış')
        ->assertSee('data-workspace-tabs', false)
        ->assertSee('data-command-palette', false);

    $items = app(AppNavigation::class)->items();
    expect(array_column($items, 'label'))->toBe(['Ana Sayfa', 'Ayarlar'])
        ->and(app(FeatureRegistry::class)->enabled(\App\Foundation\Features\FeatureKey::Customers))->toBeFalse();
});

it('allows shell branch selection only inside the active company', function (): void {
    $user = shellUser('branch-select');
    $companyA = shellCompany('SHELL-F-A');
    $companyB = shellCompany('SHELL-F-B');
    shellMembership($user, $companyA);
    $branchA = shellBranch($companyA, 'A1');
    $branchB = shellBranch($companyB, 'B1');

    $this->actingAs($user)
        ->withSession(['active_company_id' => $companyA->getKey()])
        ->post('/context/branch', ['branch_id' => $branchA->getKey()])
        ->assertRedirect('/workspace')
        ->assertSessionHas('active_branch_id', $branchA->getKey());

    $this->actingAs($user)
        ->withSession(['active_company_id' => $companyA->getKey()])
        ->post('/context/branch', ['branch_id' => $branchB->getKey()])
        ->assertNotFound();
});

it('keeps settings landing accessible without a specific settings permission', function (): void {
    $user = shellUser('settings-landing');
    $company = shellCompany('SHELL-G');
    shellMembership($user, $company);

    $this->actingAs($user)
        ->withSession(['active_company_id' => $company->getKey()])
        ->get('/settings')
        ->assertOk()
        ->assertSee('Yalnız erişim yetkiniz bulunan yönetim alanları gösterilir.');
});

function shellUser(string $suffix): User
{
    return User::query()->create([
        'name' => 'Shell '.$suffix,
        'email' => $suffix.'@shell.test',
        'password' => 'correct-password',
        'status' => UserStatus::Active,
    ]);
}

function shellCompany(string $code, CompanyStatus $status = CompanyStatus::Active): Company
{
    return Company::query()->create([
        'code' => $code,
        'name' => 'Company '.$code,
        'status' => $status,
    ]);
}

function shellMembership(User $user, Company $company): CompanyMembership
{
    return CompanyMembership::query()->create([
        'company_id' => $company->getKey(),
        'user_id' => $user->getKey(),
        'is_active' => true,
        'joined_at' => now(),
    ]);
}

function shellBranch(Company $company, string $code): Branch
{
    return Branch::query()->create([
        'company_id' => $company->getKey(),
        'code' => $code,
        'name' => 'Branch '.$code,
        'is_active' => true,
    ]);
}

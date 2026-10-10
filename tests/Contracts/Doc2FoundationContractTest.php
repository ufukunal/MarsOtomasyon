<?php

use App\Models\CompanyModel;
use App\Support\Company\CompanyDatabaseContext;
use App\Support\Company\CompanyDatabaseName;
use Illuminate\Database\Eloquent\Model;

it('keeps DOC2 inactive until company-only migration and data verification are complete', function (): void {
    expect(config('mars_doc2.mode'))->toBe('legacy')
        ->and(config('database.connections.company.database'))->toBeNull()
        ->and(config('database.connections.company.driver'))->toBe('pgsql');
});

it('requires a company context before querying DOC2 business models', function (): void {
    expect(fn () => CompanyDatabaseContext::ensure())->toThrow(LogicException::class);
});

it('defines the permanent database key without year or raw company-controlled prefixes', function (): void {
    $first = CompanyDatabaseName::forCompanyId(17);
    expect($first)->toBe('mars_company_17')
        ->and($first)->not->toContain('_2026')
        ->and(is_subclass_of(CompanyModel::class, Model::class))->toBeTrue();
});

it('contains no company DB creation, drop or migration in the DOC2 connection layer', function (): void {
    $layer = file_get_contents(base_path('app/Support/Company/CompanyDatabaseContext.php'));

    expect($layer)->not->toContain('CREATE DATABASE')
        ->not->toContain('DROP DATABASE')
        ->not->toContain('Artisan::call')
        ->toContain("DB::purge('company')")
        ->toContain("table('company_user')");
});

it('does not bind an unregistered physical company database', function (): void {
    $context = file_get_contents(base_path('app/Support/Company/CompanyDatabaseContext.php'));

    expect($context)->toContain('CompanyDatabaseRegistration::query()')
        ->toContain("where('state', 'active')")
        ->toContain('verified_at')
        ->toContain('activated_at');
});

it('keeps DOC2 planning read-only with no automatic provisioning or data migration', function (): void {
    $planner = file_get_contents(base_path('app/Console/Commands/Doc2PlanCommand.php'));

    expect($planner)->toContain('doc2:plan')
        ->toContain('legacy_period_databases')
        ->not->toContain('DROP DATABASE')
        ->not->toContain('CREATE DATABASE')
        ->not->toContain('Artisan::call');
});

it('prepares a period-free company selection screen without enabling premature DOC2 access', function (): void {
    $page = file_get_contents(base_path('app/Livewire/Pages/Auth/CompanySelection.php'));
    $view = file_get_contents(base_path('resources/views/livewire/pages/auth/company-selection.blade.php'));
    $routes = file_get_contents(base_path('routes/web.php'));

    expect($page)->toContain('CompanyDatabaseContext::activateForUser')
        ->toContain('last_company_id')
        ->not->toContain('PeriodContext::')
        ->not->toContain('accessiblePeriods');
    expect($view)->toContain('Şirket Seçimi')
        ->not->toContain('wire:model="periodId"');
    expect($routes)->toContain("config('mars_doc2.mode') === 'doc2'")
        ->toContain("'/sirket-secimi'");
});

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

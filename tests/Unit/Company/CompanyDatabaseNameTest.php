<?php

use App\Support\Company\CompanyDatabaseName;

it('builds one stable non-yearly PostgreSQL identifier per company', function (): void {
    expect(CompanyDatabaseName::forCompanyId(1))->toBe('mars_company_1')
        ->and(CompanyDatabaseName::forCompanyId(19))->toBe('mars_company_19')
        ->and(CompanyDatabaseName::forCompanyId(2026))->toBe('mars_company_2026')
        ->and(CompanyDatabaseName::forCompanyId(1))->not->toBe(CompanyDatabaseName::forCompanyId(2));
});

it('rejects missing or nonpositive company identifiers', function (): void {
    expect(fn () => CompanyDatabaseName::forCompanyId(0))->toThrow(InvalidArgumentException::class)
        ->and(fn () => CompanyDatabaseName::forCompanyId(-4))->toThrow(InvalidArgumentException::class);
});

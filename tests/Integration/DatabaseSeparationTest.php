<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\IsolatedPostgres;

it('keeps tenant master auth schema away from period stock and transaction tables', function (): void {
    IsolatedPostgres::withActivePeriod(function (int $companyId, int $periodId): void {
        expect(Schema::connection('master')->hasTable('users'))->toBeTrue();
        expect(Schema::connection('master')->hasTable('stock_balances'))->toBeFalse();
        expect(Schema::connection('period')->hasTable('users'))->toBeFalse();
        expect(Schema::connection('period')->hasTable('stock_balances'))->toBeTrue();
        expect((int) DB::connection('master')->table('periods')->whereKey($periodId)->value('company_id'))
            ->toBe($companyId);
        expect((string) DB::connection('master')->getDatabaseName())->toBe('mars_test_master')
            ->and((string) DB::connection('period')->getDatabaseName())->toBe('mars_test_period');
    });
});

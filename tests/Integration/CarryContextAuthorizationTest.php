<?php

use App\Actions\Periods\CarryPeriod;
use App\Actions\Periods\PreviewPeriodCarry;
use App\Models\Period;
use Illuminate\Auth\Access\AuthorizationException;
use Tests\Support\IsolatedPostgres;

it('rejects cross-company carry preview before reading source-period business tables', function (): void {
    IsolatedPostgres::withActivePeriod(function (int $companyId): void {
        $foreign = new Period([
            'company_id' => $companyId + 100000,
            'year' => 2026,
            'status' => 'active',
            'database_name' => 'never_access_foreign',
        ]);
        $foreign->id = 999999;

        expect(fn () => app(PreviewPeriodCarry::class)->handle($foreign, 2027))
            ->toThrow(AuthorizationException::class);
    });
});

it('rejects cross-company period carry before invoking backups or provisioning databases', function (): void {
    IsolatedPostgres::withActivePeriod(function (int $companyId): void {
        $foreign = new Period([
            'company_id' => $companyId + 100000,
            'year' => 2026,
            'status' => 'active',
            'database_name' => 'never_access_foreign',
        ]);
        $foreign->id = 999998;

        expect(fn () => app(CarryPeriod::class)->handle($foreign, 2027, 'v4-unauthorized'))
            ->toThrow(AuthorizationException::class);
    });
});

<?php

use App\Actions\Periods\EnsurePeriodOpen;
use App\Exceptions\PeriodClosedException;
use App\Exceptions\PeriodYearMismatchException;
use App\Exceptions\StaleRecordException;
use App\Models\Company;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\Support\IsolatedPostgres;

it('rejects dates from another year and postings in closed accounting months', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        $action = app(EnsurePeriodOpen::class);
        $action->handle(CarbonImmutable::parse('2026-10-10'));
        expect(fn () => $action->handle(CarbonImmutable::parse('2025-10-10')))
            ->toThrow(PeriodYearMismatchException::class);
        DB::connection('period')->table('posting_periods')->insert([
            'year' => 2026,
            'month' => 10,
            'status' => 'closed',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        expect(fn () => $action->handle(CarbonImmutable::parse('2026-10-10')))
            ->toThrow(PeriodClosedException::class);
    });
});

it('increments versions and refuses stale concurrent master updates', function (): void {
    IsolatedPostgres::withActivePeriod(function (int $companyId): void {
        $company = Company::query()->findOrFail($companyId);
        $new = $company->updateWithVersion(['name' => 'Updated by V4'], 1);
        expect((int) $new->version)->toBe(2);
        expect(fn () => $company->updateWithVersion(['name' => 'Stale writer'], 1))
            ->toThrow(StaleRecordException::class);
        expect(Company::query()->findOrFail($companyId)->name)->toBe('Updated by V4');
    });
});

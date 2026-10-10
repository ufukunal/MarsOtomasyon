<?php

use App\Actions\Periods\CarryOpenOrders;
use App\Actions\Periods\CopyPeriodCards;
use App\Actions\Periods\CopyPeriodOpenings;
use App\Models\Period;
use Tests\Support\IsolatedPostgres;

it('refuses different-company or non-consecutive-year period snapshots before touching source PostgreSQL', function (string $action, int $sourceCompany, int $targetCompany, int $sourceYear, int $targetYear): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        $source = new Period([
            'company_id' => $sourceCompany,
            'year' => $sourceYear,
            'status' => 'closed',
            'database_name' => 'never_connect_to_source',
        ]);
        $source->id = 88;

        $target = new Period([
            'company_id' => $targetCompany,
            'year' => $targetYear,
            'status' => 'active',
            'database_name' => 'never_connect_to_target',
        ]);
        $target->id = 99;

        expect(fn () => app($action)->handle($source, $target))->toThrow(DomainException::class);
    });
})->with([
    'orders company mismatch' => [CarryOpenOrders::class, 1, 2, 2026, 2027],
    'orders year gap' => [CarryOpenOrders::class, 1, 1, 2026, 2028],
    'cards company mismatch' => [CopyPeriodCards::class, 1, 2, 2026, 2027],
    'cards year gap' => [CopyPeriodCards::class, 1, 1, 2026, 2025],
    'openings company mismatch' => [CopyPeriodOpenings::class, 1, 2, 2026, 2027],
    'openings year gap' => [CopyPeriodOpenings::class, 1, 1, 2026, 2029],
]);

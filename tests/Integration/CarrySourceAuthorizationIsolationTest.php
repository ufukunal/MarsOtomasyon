<?php

use App\Actions\Channels\CarryChannelPeriodState;
use App\Actions\Imports\CarryImportFileFromPeriod;
use App\Models\Period;
use Illuminate\Auth\Access\AuthorizationException;
use Tests\Support\AuthorizedPeriod;
use Tests\Support\IsolatedPostgres;

it('denies foreign-company import carry before loading any foreign source database', function (): void {
    IsolatedPostgres::withActivePeriod(function (int $companyId): void {
        AuthorizedPeriod::login();
        try {
            $source = new Period([
                'company_id' => $companyId + 100,
                'year' => 2025,
                'status' => 'closed',
                'database_name' => 'foreign_never_opened',
            ]);
            $source->id = 100000;

            $method = new ReflectionMethod(CarryImportFileFromPeriod::class, 'assertSourcePeriod');
            $action = (new ReflectionClass(CarryImportFileFromPeriod::class))->newInstanceWithoutConstructor();
            expect(fn () => $method->invoke($action, $source))->toThrow(DomainException::class);
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});

it('blocks an otherwise valid source-period import carry when the actor has no source-period grant', function (): void {
    IsolatedPostgres::withActivePeriod(function (int $companyId, int $targetPeriodId): void {
        AuthorizedPeriod::login();
        try {
            $source = new Period([
                'company_id' => $companyId,
                'year' => 2025,
                'status' => 'closed',
                'database_name' => 'ungranted_source_period',
            ]);
            $source->id = $targetPeriodId + 100000;

            $method = new ReflectionMethod(CarryImportFileFromPeriod::class, 'assertSourcePeriod');
            $action = (new ReflectionClass(CarryImportFileFromPeriod::class))->newInstanceWithoutConstructor();
            expect(fn () => $method->invoke($action, $source))->toThrow(AuthorizationException::class);
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});

it('rejects cross-company and non-consecutive channel carry periods before reading source SQL', function (int $fromCompany, int $toCompany, int $fromYear, int $toYear): void {
    $source = new Period(['company_id' => $fromCompany, 'year' => $fromYear, 'status' => 'closed']);
    $target = new Period(['company_id' => $toCompany, 'year' => $toYear, 'status' => 'active']);
    $source->id = 1000;
    $target->id = 1001;

    $action = (new ReflectionClass(CarryChannelPeriodState::class))->newInstanceWithoutConstructor();
    $method = new ReflectionMethod(CarryChannelPeriodState::class, 'assertPeriods');

    expect(fn () => $method->invoke($action, $source, $target))->toThrow(DomainException::class);
})->with([
    'another company' => [1, 2, 2025, 2026],
    'non-consecutive year' => [1, 1, 2025, 2027],
    'backwards year' => [1, 1, 2026, 2025],
]);

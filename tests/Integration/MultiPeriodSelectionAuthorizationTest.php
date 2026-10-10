<?php

use App\Models\Period;
use App\Support\Reporting\MultiPeriod\PeriodRangeSelector;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\AuthorizedPeriod;
use Tests\Support\IsolatedPostgres;

function marsV4ReportTestPeriod(int $companyId, int $year, string $status = 'closed'): Period
{
    return Period::query()->create([
        'company_id' => $companyId,
        'year' => $year,
        'starts_on' => "{$year}-01-01",
        'ends_on' => "{$year}-12-31",
        'database_name' => 'mars_test_period',
        'status' => $status,
    ]);
}

it('rejects periods not explicitly allowed to the requesting user even within their own company', function (): void {
    IsolatedPostgres::withActivePeriod(function (int $companyId, int $periodId): void {
        $actor = AuthorizedPeriod::login();

        try {
            $db = DB::connection('master');
            $db->table('company_user')->insert([
                'company_id' => $companyId, 'user_id' => $actor->id,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $db->table('period_user_access')->insert([
                'period_id' => $periodId, 'user_id' => $actor->id,
                'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $other = marsV4ReportTestPeriod($companyId, 2027);

            expect(fn () => app(PeriodRangeSelector::class)
                ->select($actor, $companyId, [$periodId, $other->id]))
                ->toThrow(AuthorizationException::class);

            expect(app(PeriodRangeSelector::class)->available($actor, $companyId)
                ->pluck('id')->all())->toBe([$periodId]);
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});

it('rejects archived and missing periods while normalizing duplicate and invalid selections', function (): void {
    IsolatedPostgres::withActivePeriod(function (int $companyId, int $periodId): void {
        $actor = AuthorizedPeriod::login();

        try {
            $db = DB::connection('master');
            $db->table('company_user')->insert([
                'company_id' => $companyId, 'user_id' => $actor->id,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $db->table('period_user_access')->insert([
                'period_id' => $periodId, 'user_id' => $actor->id,
                'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $select = app(PeriodRangeSelector::class);
            expect($select->select($actor, $companyId, [$periodId, (string) $periodId, 0]))
                ->toHaveCount(1);

            $archived = marsV4ReportTestPeriod($companyId, 2027, 'archived');
            $db->table('period_user_access')->insert([
                'period_id' => $archived->id, 'user_id' => $actor->id,
                'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);

            expect(fn () => $select->select($actor, $companyId, [$archived->id]))
                ->toThrow(DomainException::class);
            expect(fn () => $select->select($actor, $companyId, []))
                ->toThrow(DomainException::class);
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});

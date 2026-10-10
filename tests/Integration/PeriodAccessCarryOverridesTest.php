<?php

use App\Actions\Periods\CopyPeriodAccess;
use App\Models\Period;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\AuthorizedPeriod;
use Tests\Support\IsolatedPostgres;

function marsV4CarriedPeriod(int $companyId, int $sourceId): Period
{
    return Period::query()->create([
        'company_id' => $companyId,
        'year' => 2027,
        'starts_on' => '2027-01-01',
        'ends_on' => '2027-12-31',
        'database_name' => 'mars_v4_future_'.strtolower(Str::random(12)),
        'status' => 'active',
        'carried_from_period_id' => $sourceId,
        'carried_at' => now(),
    ]);
}

it('copies only explicitly selected company members with their original period permission overrides', function (): void {
    IsolatedPostgres::withActivePeriod(function (int $companyId, int $sourceId): void {
        $actor = AuthorizedPeriod::login();

        try {
            $db = DB::connection('master');
            $db->table('company_user')->insert([
                'company_id' => $companyId, 'user_id' => $actor->id,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $overrides = ['allow' => ['stock.view'], 'deny' => ['sales_invoices.post']];
            $db->table('period_user_access')->insert([
                'period_id' => $sourceId,
                'user_id' => $actor->id,
                'permission_overrides' => json_encode($overrides, JSON_THROW_ON_ERROR),
                'is_active' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);

            $target = marsV4CarriedPeriod($companyId, $sourceId);
            $source = Period::query()->findOrFail($sourceId);

            $result = app(CopyPeriodAccess::class)->handle(
                $source, $target, [$actor->id, $actor->id, 0, -1],
            );

            expect($result)->toBe(['copied' => 1, 'user_ids' => [$actor->id]]);

            $copied = $db->table('period_user_access')
                ->where('period_id', $target->id)
                ->where('user_id', $actor->id)->first();
            expect($copied)->not->toBeNull()
                ->and((bool) $copied->is_active)->toBeTrue()
                ->and(json_decode($copied->permission_overrides, true))->toBe($overrides);

            app(CopyPeriodAccess::class)->handle($source, $target, [$actor->id]);
            expect($db->table('period_user_access')->where('period_id', $target->id)->count())->toBe(1);
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});

it('rejects carrying access for users without active source-period permission', function (): void {
    IsolatedPostgres::withActivePeriod(function (int $companyId, int $sourceId): void {
        $actor = AuthorizedPeriod::login();

        try {
            $source = Period::query()->findOrFail($sourceId);
            $target = marsV4CarriedPeriod($companyId, $sourceId);

            expect(fn () => app(CopyPeriodAccess::class)->handle($source, $target, [$actor->id]))
                ->toThrow(DomainException::class);

            expect(DB::connection('master')->table('period_user_access')
                ->where('period_id', $target->id)->count())->toBe(0);
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});

it('rejects applying period access overrides before a completed carry is recorded', function (): void {
    IsolatedPostgres::withActivePeriod(function (int $companyId, int $sourceId): void {
        AuthorizedPeriod::login();

        try {
            $source = Period::query()->findOrFail($sourceId);
            $target = new Period(['company_id' => $companyId, 'year' => 2027, 'status' => 'active']);

            expect(fn () => app(CopyPeriodAccess::class)->handle($source, $target, [1]))
                ->toThrow(DomainException::class);
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});

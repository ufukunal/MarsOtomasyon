<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\IsolatedPostgres;

it('rolls back both tenant and master inserts and clears period context when an integration fixture fails', function (): void {
    IsolatedPostgres::approved();

    $master = DB::connection('master');
    $beforeCompanies = $master->table('companies')->count();
    $marker = 'V4-ROLLBACK-'.Str::random(12);
    $periodTableCount = null;

    try {
        IsolatedPostgres::withActivePeriod(function () use ($marker, &$periodTableCount): void {
            $db = DB::connection('period');
            $periodTableCount = $db->table('units')->count();

            $db->table('units')->insert([
                'code' => $marker,
                'name' => 'Must roll back',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            throw new RuntimeException('Intentional isolated fixture failure');
        });
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('Intentional isolated fixture failure');
    }

    expect($master->table('companies')->count())->toBe($beforeCompanies)
        ->and(DB::connection('period')->table('units')->where('code', $marker)->exists())->toBeFalse()
        ->and(\App\Support\Period\PeriodContext::periodId())->toBeNull()
        ->and($periodTableCount)->not->toBeNull();
});

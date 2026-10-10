<?php

use App\Actions\Periods\CarryPeriod;
use App\Actions\Periods\CreatePeriod;
use App\Jobs\CarryPeriodJob;
use App\Jobs\CreatePeriodJob;
use Illuminate\Support\Facades\DB;
use Tests\Support\IsolatedPostgres;

it('rejects a period-creation queue job for an actor that does not exist', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        $before = DB::connection('master')->table('periods')->count();
        $job = new CreatePeriodJob(actorId: 99999999, companyId: 1, year: 2027);
        $action = (new ReflectionClass(CreatePeriod::class))->newInstanceWithoutConstructor();

        expect(fn () => $job->handle($action))->toThrow(RuntimeException::class);
        expect(DB::connection('master')->table('periods')->count())->toBe($before);
    });
});

it('rejects a period-carry queue job for a removed actor without changing period state', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        $before = DB::connection('master')->table('periods')->count();
        $job = new CarryPeriodJob(actorId: 99999998, sourcePeriodId: 1, targetYear: 2027, idempotencyKey: 'v4-closed');
        $action = (new ReflectionClass(CarryPeriod::class))->newInstanceWithoutConstructor();

        expect(fn () => $job->handle($action))->toThrow(RuntimeException::class);
        expect(DB::connection('master')->table('periods')->count())->toBe($before);
    });
});

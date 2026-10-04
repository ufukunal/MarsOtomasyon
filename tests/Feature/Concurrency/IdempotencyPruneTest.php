<?php

use App\Support\Period\PeriodContext;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

it('idempotency prune master ve period kayıtlarında done retention ve stale processing recovery uygular', function () {
    [$company, $period] = $this->createCompanyWithPeriod('IDEMPRUNE');

    DB::connection('period')->table('idempotency_keys')->insert([
        [
            'key' => 'old-done-key',
            'action' => 'test.old',
            'status' => 'done',
            'result' => json_encode(['value' => 1], JSON_THROW_ON_ERROR),
            'completed_at' => now()->subDays(8),
            'created_at' => now()->subDays(8),
            'updated_at' => now()->subDays(8),
        ],
        [
            'key' => 'recent-done-key',
            'action' => 'test.recent',
            'status' => 'done',
            'result' => json_encode(['value' => 2], JSON_THROW_ON_ERROR),
            'completed_at' => now()->subDay(),
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ],
        [
            'key' => 'stale-processing-period',
            'action' => 'test.processing.period.stale',
            'status' => 'processing',
            'result' => null,
            'completed_at' => null,
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ],
        [
            'key' => 'recent-processing-period',
            'action' => 'test.processing.period.recent',
            'status' => 'processing',
            'result' => null,
            'completed_at' => null,
            'created_at' => now()->subHours(2),
            'updated_at' => now()->subHours(2),
        ],
    ]);

    DB::connection('master')->table('idempotency_keys')->insert([
        [
            'key' => 'stale-processing-master',
            'action' => 'test.processing.master.stale',
            'status' => 'processing',
            'result' => null,
            'completed_at' => null,
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ],
        [
            'key' => 'recent-processing-master',
            'action' => 'test.processing.master.recent',
            'status' => 'processing',
            'result' => null,
            'completed_at' => null,
            'created_at' => now()->subHours(2),
            'updated_at' => now()->subHours(2),
        ],
    ]);

    PeriodContext::clear();

    expect(auth()->check())->toBeFalse()
        ->and(Artisan::call('idempotency:prune'))->toBe(0);

    expect(DB::connection('master')->table('idempotency_keys')->where('key', 'stale-processing-master')->exists())->toBeFalse()
        ->and(DB::connection('master')->table('idempotency_keys')->where('key', 'recent-processing-master')->exists())->toBeTrue();

    PeriodContext::useSystem($company->id, $period->id);

    expect(DB::connection('period')->table('idempotency_keys')->where('key', 'old-done-key')->exists())->toBeFalse()
        ->and(DB::connection('period')->table('idempotency_keys')->where('key', 'recent-done-key')->exists())->toBeTrue()
        ->and(DB::connection('period')->table('idempotency_keys')->where('key', 'stale-processing-period')->exists())->toBeFalse()
        ->and(DB::connection('period')->table('idempotency_keys')->where('key', 'recent-processing-period')->exists())->toBeTrue();
});

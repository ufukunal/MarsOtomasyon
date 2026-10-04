<?php

use App\Support\Period\PeriodContext;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

it('idempotency prune console contextinde kullanıcı oturumu olmadan çalışır', function () {
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
            'completed_at' => now()->subDays(1),
            'created_at' => now()->subDays(1),
            'updated_at' => now()->subDays(1),
        ],
    ]);

    PeriodContext::clear();

    expect(auth()->check())->toBeFalse()
        ->and(Artisan::call('idempotency:prune'))->toBe(0);

    PeriodContext::useSystem($company->id, $period->id);

    expect(DB::connection('period')->table('idempotency_keys')->where('key', 'old-done-key')->exists())->toBeFalse()
        ->and(DB::connection('period')->table('idempotency_keys')->where('key', 'recent-done-key')->exists())->toBeTrue();
});

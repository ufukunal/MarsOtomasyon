<?php

use App\Models\SalesChannelAccount;
use App\Support\Channels\ChannelSyncRecorder;
use Illuminate\Support\Facades\DB;
use Tests\Support\IsolatedPostgres;

function v4RetryChannelAccount(int $companyId): SalesChannelAccount
{
    return SalesChannelAccount::query()->create([
        'company_id' => $companyId,
        'platform' => 'woocommerce',
        'name' => 'V4 isolated retry account',
        'credentials_encrypted' => [
            'consumer_key' => 'ck_v4_only',
            'consumer_secret' => 'cs_v4_private_test_secret',
        ],
        'is_active' => true,
    ]);
}

it('recovers stale processing events but blocks a second active worker claim', function (): void {
    IsolatedPostgres::withActivePeriod(function (int $companyId): void {
        $account = v4RetryChannelAccount($companyId);
        $recorder = app(ChannelSyncRecorder::class);
        $queued = $recorder->queue($account->id, 'outbound', 'product', 'stock', 10);
        $running = $recorder->startAttempt($queued);

        expect($running->attempts)->toBe(1);
        expect(fn () => $recorder->startAttempt($running))->toThrow(DomainException::class);

        DB::connection('period')->table('channel_sync_events')->where('id', $queued->id)
            ->update(['last_attempt_at' => now()->subMinutes(16)]);

        $reclaimed = $recorder->startAttempt($running);
        expect($reclaimed->status)->toBe('processing')
            ->and($reclaimed->attempts)->toBe(2)
            ->and(DB::connection('period')->table('channel_sync_events')
                ->where('id', $queued->id)->count())->toBe(1);
    });
});

it('uses bounded retry delays then stores one sanitized permanent channel error', function (): void {
    IsolatedPostgres::withActivePeriod(function (int $companyId): void {
        $account = v4RetryChannelAccount($companyId);
        $recorder = app(ChannelSyncRecorder::class);
        $old = config('channels.retry_delays');
        config(['channels.retry_delays' => [10, 20]]);

        try {
            $event = $recorder->queue($account->id, 'outbound', 'product', 'price', 33);
            $first = $recorder->startAttempt($event);
            $delay1 = $recorder->markFailure($first, 'consumer_secret=cs_v4_private_test_secret');

            expect($delay1)->toBe(10)
                ->and($first->refresh()->error_summary)->not->toContain('cs_v4_private_test_secret');

            $second = $recorder->startAttempt($first);
            expect($recorder->markFailure($second, 'HTTP 503'))->toBe(20);

            $third = $recorder->startAttempt($second);
            expect($recorder->markFailure($third, 'HTTP 503'))->toBeNull();

            expect(DB::connection('period')->table('channel_sync_errors')
                ->where('channel_sync_event_id', $event->id)->count())->toBe(1);
        } finally {
            config(['channels.retry_delays' => $old]);
        }
    });
});

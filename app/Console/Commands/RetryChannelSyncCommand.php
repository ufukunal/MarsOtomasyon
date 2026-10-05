<?php

namespace App\Console\Commands;

use App\Actions\Channels\RetryChannelSyncEvent;
use App\Models\Period;
use App\Models\Period\ChannelSyncEvent;
use App\Support\Channels\ChannelAutomationActorResolver;
use App\Support\Period\PeriodContext;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

class RetryChannelSyncCommand extends Command
{
    protected $signature = 'channels:retry';

    protected $description = '30/60/120 saniye politikasına göre başarısız outbound kanal sync eventlerini yeniden dener';

    public function handle(
        RetryChannelSyncEvent $retry,
        ChannelAutomationActorResolver $actors,
    ): int {
        $failed = 0;
        $delays = array_values(array_map('intval', config('channels.retry_delays', [30, 60, 120])));

        try {
            Period::query()
                ->where('status', 'active')
                ->orderBy('company_id')
                ->orderBy('year')
                ->each(function (Period $period) use ($retry, $actors, $delays, &$failed): void {
                    PeriodContext::useSystem((int) $period->company_id, (int) $period->id);

                    ChannelSyncEvent::query()
                        ->where('status', 'failed')
                        ->where('direction', 'outbound')
                        ->whereBetween('attempts', [1, count($delays)])
                        ->whereNotNull('last_attempt_at')
                        ->orderBy('id')
                        ->limit(200)
                        ->get()
                        ->each(function (ChannelSyncEvent $event) use (
                            $retry,
                            $actors,
                            $delays,
                            &$failed,
                        ): void {
                            $index = max(0, (int) $event->attempts - 1);
                            $delay = $delays[$index] ?? null;

                            if ($delay === null
                                || CarbonImmutable::parse($event->last_attempt_at)
                                    ->addSeconds($delay)
                                    ->isFuture()) {
                                return;
                            }

                            try {
                                $actors->run(
                                    ['channel_sync.update'],
                                    fn () => $retry->handle($event, automatic: true),
                                );
                            } catch (Throwable $exception) {
                                $failed++;
                                $this->error('sync#'.$event->id.': '.$exception->getMessage());
                            }
                        });
                });
        } finally {
            PeriodContext::clear();
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}

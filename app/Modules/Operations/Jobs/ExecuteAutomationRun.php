<?php

namespace App\Modules\Operations\Jobs;

use App\Foundation\Operations\ProductionSafetyState;
use App\Modules\Operations\AutomationService;
use App\Modules\Operations\ChannelService;
use App\Modules\Operations\NotificationService;
use App\Modules\Operations\SecurityCenter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class ExecuteAutomationRun implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300, 900];

    public function __construct(public readonly int $runId) {}

    public function handle(AutomationService $automation, NotificationService $notifications, ChannelService $channels, SecurityCenter $security, ProductionSafetyState $safety): void
    {
        if (! $safety->asyncWorkEnabled()) {
            $this->release($safety->retryAfterSeconds());

            return;
        }

        $automation->execute($this->runId, $notifications, $channels, $security);
    }
}

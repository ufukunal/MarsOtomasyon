<?php

namespace App\Modules\Operations\Jobs;

use App\Foundation\Operations\ProductionSafetyState;
use App\Modules\Operations\AutomationService;
use App\Modules\Operations\ChannelDomainEventIngestor;
use App\Modules\Operations\ChannelService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class ProcessIntegrationEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [60, 300, 900, 3600];

    public function __construct(public readonly int $eventId) {}

    public function handle(ChannelDomainEventIngestor $domain, ChannelService $channels, AutomationService $automation, ProductionSafetyState $safety): void
    {
        if (! $safety->asyncWorkEnabled()) {
            $this->release($safety->retryAfterSeconds());

            return;
        }

        $domain->process($this->eventId);
        $channels->processEvent($this->eventId, $automation);
    }
}

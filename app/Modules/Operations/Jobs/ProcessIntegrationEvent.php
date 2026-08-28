<?php

namespace App\Modules\Operations\Jobs;

use App\Modules\Operations\AutomationService;
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

    public function handle(ChannelService $channels, AutomationService $automation): void
    {
        $channels->processEvent($this->eventId, $automation);
    }
}

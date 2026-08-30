<?php

namespace App\Modules\Operations\Jobs;

use App\Modules\Commerce\CommerceSyncGuard;
use App\Modules\Operations\ChannelService;
use App\Modules\Operations\ProviderRateLimitException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class ProcessIntegrationSync implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [60, 300, 900, 3600];

    public function __construct(public readonly int $effectId) {}

    public function handle(ChannelService $channels, CommerceSyncGuard $guard): void
    {
        if (! $guard->shouldSend($this->effectId)) {
            return;
        }

        try {
            $channels->processSync($this->effectId);
        } catch (ProviderRateLimitException $exception) {
            $this->release($exception->retryAfterSeconds);
        } finally {
            $guard->reconcile($this->effectId);
        }
    }
}

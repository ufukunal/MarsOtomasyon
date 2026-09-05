<?php

namespace App\Modules\Operations\Jobs;

use App\Foundation\Operations\ProductionSafetyState;
use App\Modules\Operations\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class DeliverNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [60, 300, 900, 3600];

    public function __construct(public readonly int $deliveryId) {}

    public function handle(NotificationService $notifications, ProductionSafetyState $safety): void
    {
        if (! $safety->asyncWorkEnabled()) {
            $this->release($safety->retryAfterSeconds());

            return;
        }

        $notifications->deliver($this->deliveryId);
    }
}

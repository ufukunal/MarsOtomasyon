<?php

namespace App\Modules\Commerce\MarketplacePack\Jobs;

use App\Modules\Commerce\MarketplacePack\MarketplacePackService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class ProcessMarketplacePackSync implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [60, 300, 900, 3600];

    public function __construct(public readonly int $effectId) {}

    public function handle(MarketplacePackService $service): void
    {
        $service->processSync($this->effectId);
    }
}

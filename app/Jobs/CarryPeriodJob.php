<?php

namespace App\Jobs;

use App\Actions\Periods\CarryPeriod;
use App\Models\Period;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

class CarryPeriodJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $timeout = 7200;
    public int $uniqueFor = 7200;

    public function __construct(
        public readonly int $actorId,
        public readonly int $sourcePeriodId,
        public readonly int $targetYear,
        public readonly string $idempotencyKey,
    ) {
        $this->onQueue('operations');
    }

    public function uniqueId(): string
    {
        return $this->sourcePeriodId.':'.$this->targetYear;
    }

    public function handle(CarryPeriod $action): void
    {
        $actor = User::query()->where('is_active', true)->find($this->actorId);

        if (! $actor) {
            throw new RuntimeException('Dönem devri actor kullanıcısı aktif değil.');
        }

        Auth::login($actor);

        try {
            $source = Period::query()->findOrFail($this->sourcePeriodId);
            $action->handle($source, $this->targetYear, $this->idempotencyKey);
        } finally {
            Auth::logout();
        }
    }
}

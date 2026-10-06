<?php

namespace App\Jobs;

use App\Actions\Periods\CreatePeriod;
use App\Models\Company;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

class CreatePeriodJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 3600;

    public int $uniqueFor = 3600;

    public function __construct(
        public readonly int $actorId,
        public readonly int $companyId,
        public readonly int $year,
    ) {
        $this->onQueue('operations');
    }

    public function uniqueId(): string
    {
        return $this->companyId.':'.$this->year;
    }

    public function handle(CreatePeriod $action): void
    {
        $actor = User::query()->where('is_active', true)->find($this->actorId);

        if (! $actor) {
            throw new RuntimeException('Dönem oluşturma actor kullanıcısı aktif değil.');
        }

        Auth::login($actor);

        try {
            Gate::authorize('periods.create');
            $company = Company::query()->where('is_active', true)->findOrFail($this->companyId);
            $action->handle($company, $this->year);
        } finally {
            Auth::logout();
        }
    }
}

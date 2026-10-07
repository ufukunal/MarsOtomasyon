<?php

namespace App\Jobs;

use App\Actions\Periods\CreatePeriod;
use App\Models\Company;
use App\Models\User;
use App\Support\Auth\PeriodPermissionContext;
use App\Support\Company\CompanyContext;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

        PeriodPermissionContext::clear();
        CompanyContext::clear();
        Auth::login($actor);

        try {
            $company = Company::query()->where('is_active', true)->findOrFail($this->companyId);

            $hasAccess = DB::connection('master')
                ->table('company_user')
                ->where('company_id', $company->id)
                ->where('user_id', $actor->id)
                ->exists();

            if (! $hasAccess) {
                throw new \Illuminate\Auth\Access\AuthorizationException(
                    'Dönem oluşturma actor kullanıcısının hedef şirkete erişimi yok.',
                );
            }

            CompanyContext::use((int) $company->id);
            Gate::authorize('periods.create');

            $action->handle($company, $this->year);
        } finally {
            PeriodPermissionContext::clear();
            CompanyContext::clear();
            Auth::logout();
        }
    }
}

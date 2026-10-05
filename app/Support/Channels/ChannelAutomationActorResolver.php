<?php

namespace App\Support\Channels;

use App\Models\User;
use App\Support\Auth\MutationAuthorizer;
use App\Support\Period\PeriodContext;
use Closure;
use DomainException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

final class ChannelAutomationActorResolver
{
    /** @param list<string> $abilities */
    public function resolve(array $abilities): User
    {
        $companyId = PeriodContext::companyId();
        $periodId = PeriodContext::periodId();

        if (! $companyId || ! $periodId) {
            throw new DomainException('Kanal otomasyonu için aktif şirket/dönem bağlamı gereklidir.');
        }

        $users = User::query()
            ->where('is_active', true)
            ->whereHas('companies', fn ($query) => $query->where('companies.id', $companyId))
            ->whereHas('accessiblePeriods', fn ($query) => $query
                ->where('periods.id', $periodId)
                ->where('period_user_access.is_active', true))
            ->orderBy('id')
            ->get();

        foreach ($users as $user) {
            $allowed = true;

            foreach ($abilities as $ability) {
                if (! Gate::forUser($user)->allows($ability)) {
                    $allowed = false;
                    break;
                }
            }

            if ($allowed) {
                return $user;
            }
        }

        throw new DomainException(
            'Kanal otomasyonu için gerekli yetkilere sahip aktif şirket/dönem kullanıcısı bulunamadı.',
        );
    }

    /** @param list<string> $abilities */
    public function run(array $abilities, Closure $callback): mixed
    {
        $actor = $this->resolve($abilities);
        $previous = Auth::user();

        Auth::setUser($actor);

        try {
            return MutationAuthorizer::runAs($actor, fn () => $callback());
        } finally {
            if ($previous) {
                Auth::setUser($previous);
            } else {
                Auth::guard()->forgetUser();
            }
        }
    }
}

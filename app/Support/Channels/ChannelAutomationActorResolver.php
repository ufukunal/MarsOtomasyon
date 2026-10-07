<?php

namespace App\Support\Channels;

use App\Models\User;
use App\Support\Auth\MutationAuthorizer;
use App\Support\Auth\PeriodPermissionContext;
use App\Support\Period\PeriodContext;
use Closure;
use DomainException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
            $overrides = $this->periodOverrides((int) $periodId, (int) $user->id);
            $allowed = true;

            PeriodPermissionContext::use($overrides);

            try {
                foreach ($abilities as $ability) {
                    if (! Gate::forUser($user)->allows($ability)) {
                        $allowed = false;
                        break;
                    }
                }
            } finally {
                PeriodPermissionContext::clear();
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
        $periodId = (int) PeriodContext::periodId();
        $overrides = $this->periodOverrides($periodId, (int) $actor->id);
        $previous = Auth::user();

        PeriodPermissionContext::clear();
        PeriodPermissionContext::use($overrides);
        Auth::setUser($actor);

        try {
            return MutationAuthorizer::runAs($actor, fn () => $callback());
        } finally {
            PeriodPermissionContext::clear();

            if ($previous) {
                Auth::setUser($previous);
            } else {
                Auth::guard()->forgetUser();
            }
        }
    }

    /** @return array<string,mixed> */
    private function periodOverrides(int $periodId, int $userId): array
    {
        $raw = DB::connection('master')
            ->table('period_user_access')
            ->where('period_id', $periodId)
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->value('permission_overrides');

        if (is_array($raw)) {
            return $raw;
        }

        if (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

}

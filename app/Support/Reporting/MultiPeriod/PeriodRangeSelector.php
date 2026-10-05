<?php

namespace App\Support\Reporting\MultiPeriod;

use App\Models\Period;
use App\Models\User;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final class PeriodRangeSelector
{
    /** @return Collection<int,Period> */
    public function available(User $actor, int $companyId): Collection
    {
        $this->assertCompanyAccess($actor, $companyId);

        $periodIds = DB::connection('master')
            ->table('period_user_access')
            ->where('user_id', $actor->id)
            ->where('is_active', true)
            ->pluck('period_id')
            ->all();

        return Period::query()
            ->where('company_id', $companyId)
            ->whereIn('id', $periodIds)
            ->orderBy('year')
            ->get();
    }

    /** @param list<int|string> $periodIds @return list<Period> */
    public function select(User $actor, int $companyId, array $periodIds): array
    {
        $this->assertCompanyAccess($actor, $companyId);

        $ids = array_values(array_unique(array_filter(
            array_map('intval', $periodIds),
            fn (int $id): bool => $id > 0,
        )));

        if ($ids === []) {
            throw new DomainException('En az bir dönem seçilmelidir.');
        }

        $max = max(1, (int) config('reporting.max_consolidated_periods', 12));

        if (count($ids) > $max) {
            throw new DomainException("En fazla {$max} dönem birlikte raporlanabilir.");
        }

        $periods = Period::query()
            ->where('company_id', $companyId)
            ->whereIn('id', $ids)
            ->orderBy('year')
            ->get();

        if ($periods->count() !== count($ids)) {
            throw new DomainException('Seçilen dönemlerden biri bu şirkete ait değil veya bulunamadı.');
        }

        $allowedIds = DB::connection('master')
            ->table('period_user_access')
            ->where('user_id', $actor->id)
            ->where('is_active', true)
            ->whereIn('period_id', $ids)
            ->pluck('period_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $missingAccess = array_values(array_diff($ids, $allowedIds));

        if ($missingAccess !== []) {
            throw new AuthorizationException('Seçilen dönemlerden en az birine erişiminiz yok.');
        }

        return $periods->all();
    }

    private function assertCompanyAccess(User $actor, int $companyId): void
    {
        $allowed = DB::connection('master')
            ->table('company_user')
            ->where('company_id', $companyId)
            ->where('user_id', $actor->id)
            ->exists();

        if (! $allowed) {
            throw new AuthorizationException('Şirket raporlarına erişiminiz yok.');
        }
    }
}

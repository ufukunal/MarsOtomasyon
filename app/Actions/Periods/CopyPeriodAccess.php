<?php

namespace App\Actions\Periods;

use App\Models\Period;
use App\Support\Audit\AuditContext;
use App\Support\Period\PeriodContext;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class CopyPeriodAccess
{
    /**
     * @param  list<int>  $userIds
     * @return array{copied:int,user_ids:list<int>}
     */
    public function handle(Period $source, Period $target, array $userIds): array
    {
        PeriodContext::ensure();

        if ((int) PeriodContext::companyId() !== (int) $source->company_id) {
            throw new AuthorizationException('Erişim kopyası kaynak dönemi aktif şirket bağlamına ait olmalıdır.');
        }

        Gate::authorize('periods.update');

        if ((int) $source->company_id !== (int) $target->company_id
            || (int) $target->year !== (int) $source->year + 1
            || (int) $target->carried_from_period_id !== (int) $source->id
            || $target->carried_at === null) {
            throw new DomainException('Period erişimi yalnız tamamlanmış ardışık carry sonrasında kopyalanabilir.');
        }

        $userIds = array_values(array_unique(array_filter(
            array_map('intval', $userIds),
            fn (int $id): bool => $id > 0,
        )));

        if ($userIds === []) {
            return ['copied' => 0, 'user_ids' => []];
        }

        $rows = DB::connection('master')
            ->table('period_user_access')
            ->join('company_user', function ($join) use ($source): void {
                $join->on('company_user.user_id', '=', 'period_user_access.user_id')
                    ->where('company_user.company_id', '=', (int) $source->company_id);
            })
            ->where('period_user_access.period_id', $source->id)
            ->where('period_user_access.is_active', true)
            ->whereIn('period_user_access.user_id', $userIds)
            ->get([
                'period_user_access.user_id',
                'period_user_access.permission_overrides',
            ]);

        $found = $rows->pluck('user_id')->map(fn ($id): int => (int) $id)->all();
        $missing = array_values(array_diff($userIds, $found));

        if ($missing !== []) {
            throw new DomainException(
                'Kaynak dönemde aktif erişimi olmayan kullanıcılar seçildi: '.implode(', ', $missing),
            );
        }

        DB::connection('master')->transaction(function () use ($target, $rows): void {
            foreach ($rows as $row) {
                DB::connection('master')->table('period_user_access')->updateOrInsert(
                    [
                        'period_id' => (int) $target->id,
                        'user_id' => (int) $row->user_id,
                    ],
                    [
                        'is_active' => true,
                        'permission_overrides' => $row->permission_overrides,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ],
                );
            }
        }, attempts: 3);

        AuditContext::master(
            'Dönem erişim ve permission override kayıtları seçili kullanıcılara taşındı.',
            [
                'source_period_id' => (int) $source->id,
                'target_period_id' => (int) $target->id,
                'user_ids' => $found,
            ],
            $target,
            'period_access_carried',
        );

        return ['copied' => count($found), 'user_ids' => $found];
    }
}

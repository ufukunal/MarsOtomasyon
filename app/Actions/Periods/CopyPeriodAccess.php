<?php

namespace App\Actions\Periods;

use App\Models\Period;
use App\Support\Audit\AuditContext;
use DomainException;
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
            ->where('period_id', $source->id)
            ->where('is_active', true)
            ->whereIn('user_id', $userIds)
            ->get();

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

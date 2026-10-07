<?php

namespace App\Actions\Periods;

use App\Models\Period;
use App\Support\Audit\AuditContext;
use App\Support\Period\PeriodContext;
use App\Support\Period\SourcePeriodContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

final class ClosePeriod
{
    public function handle(Period $period): Period
    {
        if ((int) PeriodContext::companyId() !== (int) $period->company_id) {
            throw new AuthorizationException('Dönem kapatma yalnız aktif şirkete ait dönem için yapılabilir.');
        }

        Gate::authorize('periods.cancel');

        if ($period->status === 'archived') {
            throw new \DomainException('Arşivlenmiş dönem doğrudan kapatılamaz.');
        }

        if ($period->status === 'closed') {
            return $period;
        }

        SourcePeriodContext::usePeriod($period);

        try {
            if (Schema::connection('period_source')->hasTable('production_orders')
                && DB::connection('period_source')->table('production_orders')
                    ->whereIn('status', ['draft', 'confirmed', 'in_progress'])
                    ->exists()) {
                throw new \DomainException(
                    'Dönem kapatılamaz: açık üretim/fason emri tamamlanmalı veya iptal edilmelidir.',
                );
            }
        } finally {
            SourcePeriodContext::clear();
        }

        $expectedVersion = (int) $period->version;

        $period = $period->updateWithVersion([
            'status' => 'closed',
            'closed_at' => now(),
        ], $expectedVersion);

        AuditContext::master(
            'Dönem kapatıldı.',
            ['period_id' => $period->id, 'year' => $period->year],
            $period,
            'period_closed',
        );

        return $period;
    }
}

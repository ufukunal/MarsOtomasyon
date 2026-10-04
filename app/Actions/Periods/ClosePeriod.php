<?php

namespace App\Actions\Periods;

use App\Models\Period;
use App\Support\Audit\AuditContext;
use Illuminate\Support\Facades\Gate;

final class ClosePeriod
{
    public function handle(Period $period): Period
    {
        Gate::authorize('periods.cancel');

        if ($period->status === 'archived') {
            throw new \DomainException('Arşivlenmiş dönem doğrudan kapatılamaz.');
        }

        if ($period->status === 'closed') {
            return $period;
        }

        $expectedVersion = (int) $period->version;

        $period->updateWithVersion([
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

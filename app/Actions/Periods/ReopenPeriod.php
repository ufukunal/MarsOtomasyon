<?php

namespace App\Actions\Periods;

use App\Models\Period;
use App\Support\Audit\AuditContext;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class ReopenPeriod
{
    public function handle(Period $period, string $reason): Period
    {
        Gate::authorize('periods.reopen');

        $reason = trim($reason);

        if ($reason === '') {
            throw new InvalidArgumentException('Yeniden açma gerekçesi zorunludur.');
        }

        if ($period->status === 'archived') {
            throw new \DomainException('Arşivlenmiş dönem önce geri yüklenmelidir.');
        }

        if ($period->status === 'active') {
            return $period;
        }

        $expectedVersion = (int) $period->version;

        $period = $period->updateWithVersion([
            'status' => 'active',
            'closed_at' => null,
        ], $expectedVersion);

        AuditContext::master(
            'Dönem yeniden açıldı.',
            [
                'period_id' => $period->id,
                'year' => $period->year,
                'reason' => $reason,
            ],
            $period,
            'period_reopened',
        );

        return $period;
    }
}

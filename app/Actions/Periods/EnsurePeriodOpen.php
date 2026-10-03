<?php

namespace App\Actions\Periods;

use App\Exceptions\PeriodClosedException;
use App\Exceptions\PeriodYearMismatchException;
use App\Models\PostingPeriod;
use App\Support\Period\PeriodContext;
use Carbon\CarbonInterface;

final class EnsurePeriodOpen
{
    public function handle(CarbonInterface $date): void
    {
        PeriodContext::ensureWritable();

        $activeYear = PeriodContext::year();

        if ($activeYear !== $date->year) {
            throw new PeriodYearMismatchException(sprintf(
                '%d tarihi aktif %d dönemiyle eşleşmiyor.',
                $date->year,
                $activeYear,
            ));
        }

        $period = PostingPeriod::query()
            ->where('year', $date->year)
            ->where('month', $date->month)
            ->first();

        if ($period && $period->status === 'closed') {
            throw new PeriodClosedException(sprintf(
                '%02d.%d dönemi kapalı. Bu tarihe kayıt girilemez.',
                $date->month,
                $date->year,
            ));
        }
    }
}

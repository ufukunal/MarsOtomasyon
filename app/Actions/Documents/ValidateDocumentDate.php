<?php

namespace App\Actions\Documents;

use App\Actions\Periods\EnsurePeriodOpen;
use App\Exceptions\PeriodYearMismatchException;
use App\Models\Period;
use App\Support\Documents\DocumentDateValidationResult;
use App\Support\Period\PeriodContext;
use Carbon\CarbonInterface;

final class ValidateDocumentDate
{
    public function __construct(
        private readonly EnsurePeriodOpen $ensurePeriodOpen,
    ) {}

    public function handle(CarbonInterface $documentDate): DocumentDateValidationResult
    {
        PeriodContext::ensureWritable();

        $period = Period::query()->findOrFail(PeriodContext::periodId());

        if ($documentDate->toDateString() < $period->starts_on->toDateString()
            || $documentDate->toDateString() > $period->ends_on->toDateString()) {
            throw new PeriodYearMismatchException(sprintf(
                '%s tarihi aktif %d dönemi dışında.',
                $documentDate->format('d.m.Y'),
                $period->year,
            ));
        }

        $this->ensurePeriodOpen->handle($documentDate);

        $future = $documentDate->isAfter(now()->startOfDay());

        return new DocumentDateValidationResult(
            futureDate: $future,
            warning: $future ? 'Belge tarihi bugünden ileride.' : null,
        );
    }
}

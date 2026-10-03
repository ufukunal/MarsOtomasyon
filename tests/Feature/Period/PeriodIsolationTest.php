<?php

use App\Exceptions\NoActivePeriodException;
use App\Models\NumberSeries;
use App\Models\Period\Unit;
use App\Support\Period\PeriodContext;

it('fiziksel period veritabanlarını birbirinden izole eder', function () {
    [$companyA, $periodA] = $this->createCompanyWithPeriod('ISOA');
    [$companyB, $periodB] = $this->createCompanyWithPeriod('ISOB');

    PeriodContext::useSystem($companyA->id, $periodA->id);

    $series = NumberSeries::query()->create([
        'document_type' => 'quote',
        'prefix' => 'TKL',
        'year' => 2026,
        'last_number' => 7,
        'padding' => 5,
    ]);

    expect(config('database.connections.period.database'))->toBe($periodA->database_name);

    PeriodContext::useSystem($companyB->id, $periodB->id);

    expect(config('database.connections.period.database'))->toBe($periodB->database_name)
        ->and(NumberSeries::query()->find($series->id))->toBeNull();
});

it('period context olmadan period model sorgusunu reddeder', function () {
    [$company, $period] = $this->createCompanyWithPeriod('NOCONTEXT');

    PeriodContext::release();

    expect(fn () => Unit::query()->count())
        ->toThrow(NoActivePeriodException::class);
});

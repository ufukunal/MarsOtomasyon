<?php

use App\Actions\Numbering\GenerateDocumentNumber;
use App\Actions\Periods\EnsurePeriodOpen;
use App\Exceptions\PeriodClosedException;
use App\Models\PostingPeriod;
use App\Support\Period\PeriodContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

it('belge numaralarını sırayla üretir', function () {
    [$company, $period] = $this->createCompanyWithPeriod('NUM');
    PeriodContext::useSystem($company->id, $period->id);

    $action = app(GenerateDocumentNumber::class);

    expect($action->handle('quote'))->toBe('TKL-2026-00001')
        ->and($action->handle('quote'))->toBe('TKL-2026-00002')
        ->and($action->handle('quote'))->toBe('TKL-2026-00003');
});

it('iki period sayacını bağımsız tutar', function () {
    [$companyA, $periodA] = $this->createCompanyWithPeriod('NUMA');
    [$companyB, $periodB] = $this->createCompanyWithPeriod('NUMB');
    $action = app(GenerateDocumentNumber::class);

    PeriodContext::useSystem($companyA->id, $periodA->id);
    expect($action->handle('quote'))->toBe('TKL-2026-00001');

    PeriodContext::useSystem($companyB->id, $periodB->id);
    expect($action->handle('quote'))->toBe('TKL-2026-00001');
});

it('dış transaction rollback olunca numarayı tüketmez', function () {
    [$company, $period] = $this->createCompanyWithPeriod('ROLLNUM');
    PeriodContext::useSystem($company->id, $period->id);
    $connection = DB::connection('period');
    $action = app(GenerateDocumentNumber::class);

    $connection->beginTransaction();

    try {
        expect($action->handle('quote'))->toBe('TKL-2026-00001');
    } finally {
        $connection->rollBack();
    }

    expect($action->handle('quote'))->toBe('TKL-2026-00001');
});

it('açık ayı kabul eder kapalı ayı reddeder', function () {
    [$company, $period] = $this->createCompanyWithPeriod('LOCK');
    PeriodContext::useSystem($company->id, $period->id);
    $action = app(EnsurePeriodOpen::class);

    $action->handle(CarbonImmutable::parse('2026-04-10'));

    PostingPeriod::query()->create([
        'year' => 2026,
        'month' => 5,
        'status' => 'closed',
    ]);

    expect(fn () => $action->handle(CarbonImmutable::parse('2026-05-10')))
        ->toThrow(PeriodClosedException::class);
});

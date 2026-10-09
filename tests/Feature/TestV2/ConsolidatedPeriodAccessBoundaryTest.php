<?php

use App\Support\Reporting\MultiPeriod\PeriodRangeSelector;
use Illuminate\Auth\Access\AuthorizationException;

it('v2 consolidated report selection denies a user who has no membership in target company', function () {
    [$companyA, $periodA] = $this->createCompanyWithPeriod('V2REPA');
    $actor = $this->createUserWithPeriodAccess($companyA, $periodA, 'Yönetici');
    [$companyB, $periodB] = $this->createCompanyWithPeriod('V2REPB');

    expect(fn () => app(PeriodRangeSelector::class)->select($actor, $companyB->id, [$periodB->id]))
        ->toThrow(AuthorizationException::class, 'Şirket raporlarına erişiminiz yok.');
});

it('v2 consolidated report selection rejects foreign period IDs even when company is permitted', function () {
    [$companyA, $periodA] = $this->createCompanyWithPeriod('V2REPC');
    $actor = $this->createUserWithPeriodAccess($companyA, $periodA, 'Yönetici');
    [$companyB, $periodB] = $this->createCompanyWithPeriod('V2REPD');

    expect(fn () => app(PeriodRangeSelector::class)->select($actor, $companyA->id, [$periodB->id]))
        ->toThrow(DomainException::class, 'bu şirkete ait değil');
});

it('v2 consolidated report selection refuses an empty or zero-only period list', function () {
    [$company, $period] = $this->createCompanyWithPeriod('V2REPE');
    $actor = $this->createUserWithPeriodAccess($company, $period, 'Yönetici');

    expect(fn () => app(PeriodRangeSelector::class)->select($actor, $company->id, [0, -1]))
        ->toThrow(DomainException::class, 'En az bir dönem seçilmelidir.');
});

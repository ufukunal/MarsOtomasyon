<?php

use App\Actions\ReferenceData\SaveUnitConversion;
use App\Models\Period\Unit;
use App\Support\Units\UnitConversionResolver;
use Illuminate\Validation\ValidationException;

it('ters birim dönüşümünü altı hanede yuvarlar', function () {
    [$company, $period] = $this->createCompanyWithPeriod('UNITREV');
    $admin = $this->createUserWithPeriodAccess($company, $period, 'Yönetici');
    $this->loginToPeriod($admin, $company, $period);

    $piece = Unit::query()->where('code', 'ADET')->firstOrFail();
    $box = Unit::query()->where('code', 'KUTU')->firstOrFail();

    app(SaveUnitConversion::class)->handle([
        'from_unit_id' => $box->id,
        'to_unit_id' => $piece->id,
        'factor' => '6',
    ]);

    $resolver = app(UnitConversionResolver::class);

    expect($resolver->factor($box->id, $piece->id))->toBe('6.000000')
        ->and($resolver->factor($piece->id, $box->id))->toBe('0.166667');
});

it('sıfır veya negatif birim dönüşüm katsayısını reddeder', function (string $factor) {
    [$company, $period] = $this->createCompanyWithPeriod('UNITBAD');
    $admin = $this->createUserWithPeriodAccess($company, $period, 'Yönetici');
    $this->loginToPeriod($admin, $company, $period);

    $piece = Unit::query()->where('code', 'ADET')->firstOrFail();
    $box = Unit::query()->where('code', 'KUTU')->firstOrFail();

    expect(fn () => app(SaveUnitConversion::class)->handle([
        'from_unit_id' => $box->id,
        'to_unit_id' => $piece->id,
        'factor' => $factor,
    ]))->toThrow(ValidationException::class);
})->with(['0', '-1']);

it('tanımsız birim dönüşümünde factor 1 varsaymaz', function () {
    [$company, $period] = $this->createCompanyWithPeriod('UNITMISS');
    $admin = $this->createUserWithPeriodAccess($company, $period, 'Yönetici');
    $this->loginToPeriod($admin, $company, $period);

    $piece = Unit::query()->where('code', 'ADET')->firstOrFail();
    $kg = Unit::query()->where('code', 'KG')->firstOrFail();

    expect(fn () => app(UnitConversionResolver::class)->factor($kg->id, $piece->id))
        ->toThrow(DomainException::class);
});

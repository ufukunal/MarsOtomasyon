<?php

use App\Support\Auth\PeriodPermissionContext;
use App\Support\Company\CompanyContext;
use App\Support\Period\PeriodContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

it('period override deny > allow > şirket rolü önceliğini uygular', function () {
    [$company, $period] = $this->createCompanyWithPeriod('OVERRIDE');
    $sales = $this->createUserWithPeriodAccess($company, $period, 'Satış');

    $this->loginToPeriod($sales, $company, $period);

    expect(Gate::forUser($sales)->allows('products.view'))->toBeTrue()
        ->and(Gate::forUser($sales)->allows('products.update'))->toBeFalse();

    PeriodPermissionContext::use([
        'allow' => ['products.update', 'products.view'],
        'deny' => ['products.view'],
    ]);

    expect(Gate::forUser($sales)->allows('products.update'))->toBeTrue()
        ->and(Gate::forUser($sales)->allows('products.view'))->toBeFalse();

    PeriodPermissionContext::clear();
});

it('period override Master izinlerini değiştiremez', function () {
    [$company, $period] = $this->createCompanyWithPeriod('OVMASTER');
    $admin = $this->createUserWithPeriodAccess($company, $period, 'Yönetici');
    $this->loginToPeriod($admin, $company, $period);

    PeriodPermissionContext::use([
        'deny' => ['periods.view'],
    ]);

    expect(Gate::forUser($admin)->allows('periods.view'))->toBeTrue();

    PeriodPermissionContext::clear();
});

it('is_active false period erişimini context seviyesinde keser', function () {
    [$company, $period] = $this->createCompanyWithPeriod('OVACTIVE');
    $user = $this->createUserWithPeriodAccess($company, $period, 'Yönetici');
    $this->actingAs($user);

    DB::connection('master')->table('period_user_access')
        ->where('period_id', $period->id)
        ->where('user_id', $user->id)
        ->update(['is_active' => false]);

    expect(fn () => PeriodContext::use($company->id, $period->id))
        ->toThrow(AuthorizationException::class);
});

it('Dönemler ekranında period seçilmeden şirket team contexti yetkiyi korur', function () {
    [$company, $period] = $this->createCompanyWithPeriod('TEAM');
    $admin = $this->createUserWithPeriodAccess($company, $period, 'Yönetici');

    PeriodContext::clear();

    $this->actingAs($admin)
        ->withSession([
            'active_company_id' => $company->id,
        ])
        ->get(route('settings.periods'))
        ->assertOk();
});

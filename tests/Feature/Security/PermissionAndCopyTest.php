<?php

use App\Actions\Companies\CopyRecordsBetweenCompanies;
use App\Enums\CompanyCopyPermissionType;
use App\Models\Company;
use App\Models\CompanyCopyPermission;
use App\Support\Company\CompanyContext;
use Symfony\Component\HttpKernel\Exception\HttpException;

it('Satış cost.view alamaz Yönetici alır', function () {
    [$company, $period] = $this->createCompanyWithPeriod('ROLE');

    $sales = $this->createUserWithPeriodAccess($company, $period, 'Satış');
    $admin = $this->createUserWithPeriodAccess($company, $period, 'Yönetici');

    CompanyContext::use($company->id);
    expect($sales->can('cost.view'))->toBeFalse()
        ->and($admin->can('cost.view'))->toBeTrue();
    CompanyContext::clear();
});

it('company copy permission yokken allows false döner', function () {
    $source = Company::factory()->create();
    $target = Company::factory()->create();

    expect(CompanyCopyPermission::allows(
        $source->id,
        $target->id,
        CompanyCopyPermissionType::Contact,
    ))->toBeFalse();
});

it('izin kaydı olmadan şirketler arası kopyalamayı 403 ile reddeder', function () {
    [$target, $targetPeriod] = $this->createCompanyWithPeriod('COPYT');
    $source = Company::factory()->create();
    $admin = $this->createUserWithPeriodAccess($target, $targetPeriod, 'Yönetici');

    $this->loginToPeriod($admin, $target, $targetPeriod);

    try {
        app(CopyRecordsBetweenCompanies::class)->handle(
            $source->id,
            CompanyCopyPermissionType::Contact,
            [],
        );

        $this->fail('İzinsiz copy reddedilmeliydi.');
    } catch (HttpException $exception) {
        expect($exception->getStatusCode())->toBe(403);
    }
});

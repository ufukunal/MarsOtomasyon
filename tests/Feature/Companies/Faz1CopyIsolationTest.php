<?php

use App\Actions\Companies\CopyRecordsBetweenCompanies;
use App\Enums\CompanyCopyPermissionType;
use App\Exceptions\PeriodReadOnlyException;
use App\Models\CompanyCopyPermission;
use App\Models\Period\Contact;
use App\Support\Period\PeriodContext;
use Illuminate\Validation\ValidationException;

it('izinli cari kopyasında hedefte yeni kayıt oluşturur ve kaynağı değiştirmez', function () {
    [$sourceCompany, $sourcePeriod] = $this->createCompanyWithPeriod('COPYS');
    [$targetCompany, $targetPeriod] = $this->createCompanyWithPeriod('COPYT');

    PeriodContext::useSystem($sourceCompany->id, $sourcePeriod->id);
    $source = Contact::query()->create(['title' => 'Kaynak Cari', 'type' => 'legal']);
    $sourceCode = $source->code;

    $admin = $this->createUserWithPeriodAccess($targetCompany, $targetPeriod, 'Yönetici');
    $this->loginToPeriod($admin, $targetCompany, $targetPeriod);

    CompanyCopyPermission::query()->create([
        'source_company_id' => $sourceCompany->id,
        'target_company_id' => $targetCompany->id,
        'type' => CompanyCopyPermissionType::Contact->value,
        'is_active' => true,
    ]);

    $result = app(CopyRecordsBetweenCompanies::class)->handle(
        $sourceCompany->id,
        CompanyCopyPermissionType::Contact,
        [$source->id],
    );

    expect($result->copied)->toHaveCount(1)
        ->and(Contact::query()->where('source_company_id', $sourceCompany->id)->count())->toBe(1);

    PeriodContext::useSystem($sourceCompany->id, $sourcePeriod->id);

    expect(Contact::query()->findOrFail($source->id)->code)->toBe($sourceCode)
        ->and(Contact::query()->findOrFail($source->id)->title)->toBe('Kaynak Cari');
});

it('kod çakışmasında kullanıcı kararı olmadan suffix veya overwrite yapmaz', function () {
    [$sourceCompany, $sourcePeriod] = $this->createCompanyWithPeriod('CONFS');
    [$targetCompany, $targetPeriod] = $this->createCompanyWithPeriod('CONFT');

    PeriodContext::useSystem($sourceCompany->id, $sourcePeriod->id);
    $source = Contact::query()->create(['title' => 'Kaynak', 'type' => 'legal']);

    PeriodContext::useSystem($targetCompany->id, $targetPeriod->id);
    Contact::query()->create(['title' => 'Hedef Mevcut', 'type' => 'legal']);

    $admin = $this->createUserWithPeriodAccess($targetCompany, $targetPeriod, 'Yönetici');
    $this->loginToPeriod($admin, $targetCompany, $targetPeriod);

    CompanyCopyPermission::query()->create([
        'source_company_id' => $sourceCompany->id,
        'target_company_id' => $targetCompany->id,
        'type' => CompanyCopyPermissionType::Contact->value,
        'is_active' => true,
    ]);

    $result = app(CopyRecordsBetweenCompanies::class)->handle(
        $sourceCompany->id,
        CompanyCopyPermissionType::Contact,
        [$source->id],
    );

    expect($result->copied)->toBe([])
        ->and($result->conflicts)->toHaveCount(1)
        ->and(Contact::query()->count())->toBe(1)
        ->and(Contact::query()->where('code', 'like', $source->code.'%')->count())->toBe(1);
});

it('set ve configurable ürünleri alt tanımsız kart olarak kopyalamayı reddeder', function () {
    [$sourceCompany, $sourcePeriod] = $this->createCompanyWithPeriod('SETCS');
    [$targetCompany, $targetPeriod] = $this->createCompanyWithPeriod('SETCT');

    PeriodContext::useSystem($sourceCompany->id, $sourcePeriod->id);
    $set = $this->createTestProduct(['code' => 'SRC-SET', 'kind' => 'set']);

    $admin = $this->createUserWithPeriodAccess($targetCompany, $targetPeriod, 'Yönetici');
    $this->loginToPeriod($admin, $targetCompany, $targetPeriod);

    CompanyCopyPermission::query()->create([
        'source_company_id' => $sourceCompany->id,
        'target_company_id' => $targetCompany->id,
        'type' => CompanyCopyPermissionType::Product->value,
        'is_active' => true,
    ]);

    expect(fn () => app(CopyRecordsBetweenCompanies::class)->handle(
        $sourceCompany->id,
        CompanyCopyPermissionType::Product,
        [$set->id],
    ))->toThrow(ValidationException::class);
});


it('kapalı hedef döneme şirketler arası kart kopyalamayı reddeder', function () {
    [$sourceCompany, $sourcePeriod] = $this->createCompanyWithPeriod('CLOSEDS');
    [$targetCompany, $targetPeriod] = $this->createCompanyWithPeriod('CLOSEDT');

    PeriodContext::useSystem($sourceCompany->id, $sourcePeriod->id);
    $source = Contact::query()->create(['title' => 'Kapalı Dönem Kaynak', 'type' => 'legal']);

    $admin = $this->createUserWithPeriodAccess($targetCompany, $targetPeriod, 'Yönetici');
    $targetPeriod->update(['status' => 'closed']);
    $this->loginToPeriod($admin, $targetCompany, $targetPeriod);

    CompanyCopyPermission::query()->create([
        'source_company_id' => $sourceCompany->id,
        'target_company_id' => $targetCompany->id,
        'type' => CompanyCopyPermissionType::Contact->value,
        'is_active' => true,
    ]);

    expect(fn () => app(CopyRecordsBetweenCompanies::class)->handle(
        $sourceCompany->id,
        CompanyCopyPermissionType::Contact,
        [$source->id],
    ))->toThrow(PeriodReadOnlyException::class);

    expect(Contact::query()->count())->toBe(0);
});

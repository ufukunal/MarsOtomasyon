<?php

use App\Models\User;
use App\Support\Period\PeriodContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('company erişimi olmayan authenticated kullanıcıya period context vermez', function () {
    [$company, $period] = $this->createCompanyWithPeriod('NOCO');
    $user = User::factory()->create();

    $this->actingAs($user);

    expect(fn () => PeriodContext::use($company->id, $period->id))
        ->toThrow(AuthorizationException::class);
});

it('period_user_access olmayan kullanıcıya period context vermez', function () {
    [$company, $period] = $this->createCompanyWithPeriod('NOPER');
    $user = User::factory()->create();

    DB::connection('master')->table('company_user')->insert([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($user);

    expect(fn () => PeriodContext::use($company->id, $period->id))
        ->toThrow(AuthorizationException::class);
});

it('erişim verildiğinde context kurar ve period tablolarında company_id taşımaz', function () {
    [$company, $period] = $this->createCompanyWithPeriod('ACCESS');
    $user = $this->createUserWithPeriodAccess($company, $period);

    $this->loginToPeriod($user, $company, $period);

    expect(PeriodContext::companyId())->toBe($company->id)
        ->and(PeriodContext::periodId())->toBe($period->id)
        ->and(Schema::connection('period')->hasColumn('contacts', 'company_id'))->toBeFalse()
        ->and(Schema::connection('period')->hasColumn('products', 'company_id'))->toBeFalse()
        ->and(Schema::connection('period')->hasColumn('locations', 'company_id'))->toBeFalse();
});

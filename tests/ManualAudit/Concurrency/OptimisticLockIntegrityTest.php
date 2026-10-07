<?php

use App\Actions\Periods\ClosePeriod;
use App\Actions\Periods\ReopenPeriod;
use App\Exceptions\StaleRecordException;
use Tests\ManualAudit\Support\AuditSource;
use Tests\TestCase;

uses(TestCase::class);

test('LOCK-095 yanlış expected version updatei reddeder', function () {
    [, $period] = $this->createCompanyWithPeriod('AUDL95');

    expect(fn () => $period->updateWithVersion(['status' => 'closed'], ((int) $period->version) + 1))
        ->toThrow(StaleRecordException::class);
});

test('LOCK-096 doğru version update versionı bir artırır ve refreshed model döner', function () {
    [, $period] = $this->createCompanyWithPeriod('AUDL96');
    $before = (int) $period->version;

    $updated = $period->updateWithVersion(['status' => 'closed'], $before);

    expect((int) $updated->version)->toBe($before + 1)
        ->and($updated->status)->toBe('closed');
});

test('LOCK-097 stale ikinci editor mutationı kaybedilmiş update yapamaz', function () {
    [, $period] = $this->createCompanyWithPeriod('AUDL97');
    $first = $period->fresh();
    $second = $period->fresh();

    $first->updateWithVersion(['status' => 'closed'], (int) $first->version);

    expect(fn () => $second->updateWithVersion(['status' => 'active'], (int) $second->version))
        ->toThrow(StaleRecordException::class);
});

test('LOCK-098 ClosePeriod refreshed model döndürür', function () {
    [$company, $period] = $this->createCompanyWithPeriod('AUDL98');
    $user = $this->createUserWithPeriodAccess($company, $period, 'Yönetici');
    $this->loginToPeriod($user, $company, $period);

    $closed = app(ClosePeriod::class)->handle($period->fresh());

    expect($closed->status)->toBe('closed')
        ->and($closed->closed_at)->not->toBeNull()
        ->and((int) $closed->version)->toBe((int) $period->version + 1);
});

test('LOCK-099 ClosePeriod return değeri DBdeki güncel status ile aynıdır', function () {
    [$company, $period] = $this->createCompanyWithPeriod('AUDL99');
    $user = $this->createUserWithPeriodAccess($company, $period, 'Yönetici');
    $this->loginToPeriod($user, $company, $period);

    $closed = app(ClosePeriod::class)->handle($period->fresh());

    expect($closed->status)->toBe($period->fresh()->status);
});

test('LOCK-100 ReopenPeriod refreshed model döndürür', function () {
    [$company, $period] = $this->createCompanyWithPeriod('AUDL100');
    $user = $this->createUserWithPeriodAccess($company, $period, 'Yönetici');
    $this->loginToPeriod($user, $company, $period);

    $closed = app(ClosePeriod::class)->handle($period->fresh());
    $reopened = app(ReopenPeriod::class)->handle($closed, 'Audit reopen');

    expect($reopened->status)->toBe('active')
        ->and($reopened->closed_at)->toBeNull()
        ->and((int) $reopened->version)->toBe((int) $closed->version + 1);
});

test('LOCK-101 ReopenPeriod return değeri DBdeki güncel status ile aynıdır', function () {
    [$company, $period] = $this->createCompanyWithPeriod('AUDL101');
    $user = $this->createUserWithPeriodAccess($company, $period, 'Yönetici');
    $this->loginToPeriod($user, $company, $period);

    $closed = app(ClosePeriod::class)->handle($period->fresh());
    $reopened = app(ReopenPeriod::class)->handle($closed, 'Audit reopen');

    expect($reopened->status)->toBe($period->fresh()->status);
});

test('LOCK-102 close reopen audit refreshed variable ile oluşturulur', function () {
    foreach (['app/Actions/Periods/ClosePeriod.php', 'app/Actions/Periods/ReopenPeriod.php'] as $path) {
        $source = AuditSource::read($path);
        expect($source)->toMatch('/\$period\s*=\s*\$period->updateWithVersion\(/');
        expect(strpos($source, 'AuditContext::master'))->toBeGreaterThan(strpos($source, '$period = $period->updateWithVersion'));
    }
});

test('LOCK-103 posted document lifecycle version artırır', function () {
    expect(AuditSource::read('app/Actions/Documents/PostDocument.php'))
        ->toMatch('/version\s*=\s*\(int\).*version\s*\+\s*1|updateWithVersion/');
});

test('LOCK-104 document draft mutation optimistic version veya locked row guard taşır', function () {
    $source = AuditSource::read('app/Actions/Documents/SaveSalesDocumentDraft.php');

    expect($source)->toMatch('/updateWithVersion|lockForUpdate/');
});

test('LOCK-105 production lifecycle version guard taşır', function () {
    $combined = AuditSource::read('app/Actions/Production/SaveProductionOrderDraft.php')
        .AuditSource::read('app/Actions/Production/ConfirmProductionOrder.php');

    expect($combined)->toMatch('/updateWithVersion|lockForUpdate/');
});

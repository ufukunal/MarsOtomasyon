<?php

use App\Actions\Periods\ClosePeriod;
use App\Actions\Periods\ReopenPeriod;
use App\Models\Period\Brand;
use App\Support\Concurrency\IdempotencyKey;
use Tests\TestCase;

uses(TestCase::class);

test('REG-271 nested idempotency model result retryda model tipini korur', function () {
    [$company, $period] = $this->createCompanyWithPeriod('AUDREG271');
    $user = $this->createUserWithPeriodAccess($company, $period, 'Yönetici');
    $this->loginToPeriod($user, $company, $period);

    $source = Brand::query()->create(['name' => 'Regression Source', 'is_active' => true]);
    $target = Brand::query()->create(['name' => 'Regression Target', 'is_active' => true]);

    $first = IdempotencyKey::run(
        'reg-271',
        'finance-transfer.create',
        fn () => ['source' => $source, 'target' => $target],
    );
    $retry = IdempotencyKey::run(
        'reg-271',
        'finance-transfer.create',
        fn () => throw new RuntimeException('retry callback must not execute'),
    );

    expect($first['source'])->toBeInstanceOf(Brand::class)
        ->and($retry['source'])->toBeInstanceOf(Brand::class)
        ->and($retry['target'])->toBeInstanceOf(Brand::class)
        ->and($retry['source']->id)->toBe($source->id)
        ->and($retry['target']->id)->toBe($target->id);
});

test('REG-272 ClosePeriod refreshed status version closed_at döndürür', function () {
    [$company, $period] = $this->createCompanyWithPeriod('AUDREG272');
    $user = $this->createUserWithPeriodAccess($company, $period, 'Yönetici');
    $this->loginToPeriod($user, $company, $period);
    $oldVersion = (int) $period->version;

    $result = app(ClosePeriod::class)->handle($period->fresh());

    expect($result->status)->toBe('closed')
        ->and($result->closed_at)->not->toBeNull()
        ->and((int) $result->version)->toBe($oldVersion + 1)
        ->and($result->status)->toBe($period->fresh()->status);
});

test('REG-273 ReopenPeriod refreshed status version closed_at döndürür', function () {
    [$company, $period] = $this->createCompanyWithPeriod('AUDREG273');
    $user = $this->createUserWithPeriodAccess($company, $period, 'Yönetici');
    $this->loginToPeriod($user, $company, $period);

    $closed = app(ClosePeriod::class)->handle($period->fresh());
    $closedVersion = (int) $closed->version;
    $result = app(ReopenPeriod::class)->handle($closed, 'Manual audit regression');

    expect($result->status)->toBe('active')
        ->and($result->closed_at)->toBeNull()
        ->and((int) $result->version)->toBe($closedVersion + 1)
        ->and($result->status)->toBe($period->fresh()->status);
});

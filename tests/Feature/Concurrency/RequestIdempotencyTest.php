<?php

use App\Livewire\Pages\Catalog\BrandForm;
use App\Models\Period\Brand;
use App\Support\Concurrency\IdempotencyKey;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

it('period idempotency aynı key retryında callbacki ikinci kez çalıştırmaz ve modeli yeniden yükler', function () {
    [$company, $period] = $this->createCompanyWithPeriod('IDEMPERIOD');
    $user = $this->createUserWithPeriodAccess($company, $period, 'Yönetici');
    $this->loginToPeriod($user, $company, $period);

    $runs = 0;

    $first = IdempotencyKey::run(
        'period-retry-key',
        'test.brand.create',
        function () use (&$runs): Brand {
            $runs++;

            return Brand::query()->create([
                'name' => 'Idempotent Marka',
                'is_active' => true,
            ]);
        },
    );

    $second = IdempotencyKey::run(
        'period-retry-key',
        'test.brand.create',
        function () use (&$runs): Brand {
            $runs++;

            throw new RuntimeException('Retry callback çalışmamalı.');
        },
    );

    expect($runs)->toBe(1)
        ->and($first)->toBeInstanceOf(Brand::class)
        ->and($second)->toBeInstanceOf(Brand::class)
        ->and($second->id)->toBe($first->id)
        ->and(Brand::query()->where('name', 'Idempotent Marka')->count())->toBe(1);
});

it('master idempotency aynı key retryında sonucu döndürür ve callbacki tekrarlamaz', function () {
    $runs = 0;

    $first = IdempotencyKey::runMaster(
        'master-retry-key',
        'test.master',
        function () use (&$runs): array {
            $runs++;

            return ['ok' => true, 'sequence' => $runs];
        },
    );

    $second = IdempotencyKey::runMaster(
        'master-retry-key',
        'test.master',
        function () use (&$runs): array {
            $runs++;

            return ['ok' => false, 'sequence' => $runs];
        },
    );

    expect($runs)->toBe(1)
        ->and($first)->toBe(['ok' => true, 'sequence' => 1])
        ->and($second)->toBe($first);
});

it('başarısız master mutation claimini temizler ve aynı key ile güvenli retrya izin verir', function () {
    expect(fn () => IdempotencyKey::runMaster(
        'master-failure-key',
        'test.master.failure',
        fn () => throw new RuntimeException('beklenen hata'),
    ))->toThrow(RuntimeException::class, 'beklenen hata');

    expect(
        DB::connection('master')
            ->table('idempotency_keys')
            ->where('key', 'master-failure-key')
            ->exists()
    )->toBeFalse();

    expect(IdempotencyKey::runMaster(
        'master-failure-key',
        'test.master.failure',
        fn (): string => 'retry-ok',
    ))->toBe('retry-ok');
});

it('Livewire mutation keyini ilk snapshotta taşır ve başarılı mutation sonrası döndürür', function () {
    [$company, $period] = $this->createCompanyWithPeriod('IDEMLIVE');
    $user = $this->createUserWithPeriodAccess($company, $period, 'Yönetici');
    $this->loginToPeriod($user, $company, $period);

    $component = Livewire::test(BrandForm::class);
    $before = $component->get('mutationKeys')['save'] ?? null;

    expect($before)->toBeString()->not->toBe('');

    $component
        ->set('name', 'Retry Güvenli Marka')
        ->call('save');

    $after = $component->get('mutationKeys')['save'] ?? null;

    expect($after)->toBeString()
        ->not->toBe($before)
        ->and(Brand::query()->where('name', 'Retry Güvenli Marka')->count())->toBe(1);
});

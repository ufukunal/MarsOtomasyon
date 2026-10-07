<?php

use App\Exceptions\IdempotencyInProgressException;
use App\Models\Period\Brand;
use App\Support\Concurrency\IdempotencyKey;
use Illuminate\Support\Facades\DB;
use Tests\ManualAudit\Support\AuditSource;
use Tests\TestCase;

uses(TestCase::class);

test('IDEM-076 aynı period key ve action callbacki ikinci kez çalıştırmaz', function () {
    [$company, $period] = $this->createCompanyWithPeriod('AUDI76');
    $user = $this->createUserWithPeriodAccess($company, $period, 'Yönetici');
    $this->loginToPeriod($user, $company, $period);
    $runs = 0;

    $first = IdempotencyKey::run('audit-76', 'audit.same', function () use (&$runs) {
        $runs++;

        return 'ok';
    });
    $second = IdempotencyKey::run('audit-76', 'audit.same', function () use (&$runs) {
        $runs++;

        return 'bad';
    });

    expect($runs)->toBe(1)->and($first)->toBe('ok')->and($second)->toBe('ok');
});

test('IDEM-077 aynı key farklı action ile kullanılamaz', function () {
    IdempotencyKey::runMaster('audit-77', 'audit.first', fn () => 'first');

    expect(fn () => IdempotencyKey::runMaster('audit-77', 'audit.second', fn () => 'second'))
        ->toThrow(RuntimeException::class);
});

test('IDEM-078 processing kayıt paralel retryı reddeder', function () {
    DB::connection('master')->table('idempotency_keys')->insert([
        'key' => 'audit-78',
        'action' => 'audit.processing',
        'status' => 'processing',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(fn () => IdempotencyKey::runMaster('audit-78', 'audit.processing', fn () => 'x'))
        ->toThrow(IdempotencyInProgressException::class);
});

test('IDEM-079 stale processing claim güvenli retrya açılır', function () {
    DB::connection('master')->table('idempotency_keys')->insert([
        'key' => 'audit-79',
        'action' => 'audit.stale',
        'status' => 'processing',
        'created_at' => now()->subDays(2),
        'updated_at' => now()->subDays(2),
    ]);

    expect(IdempotencyKey::runMaster('audit-79', 'audit.stale', fn () => 'recovered'))
        ->toBe('recovered');
});

test('IDEM-080 failed master callback claimi temizler', function () {
    try {
        IdempotencyKey::runMaster('audit-80', 'audit.fail', fn () => throw new RuntimeException('expected'));
    } catch (RuntimeException) {
    }

    expect(DB::connection('master')->table('idempotency_keys')->where('key', 'audit-80')->exists())
        ->toBeFalse();
});

test('IDEM-081 top level Eloquent model retryda model olarak geri yüklenir', function () {
    [$company, $period] = $this->createCompanyWithPeriod('AUDI81');
    $user = $this->createUserWithPeriodAccess($company, $period, 'Yönetici');
    $this->loginToPeriod($user, $company, $period);

    $first = IdempotencyKey::run('audit-81', 'audit.model', fn () => Brand::query()->create(['name' => 'Audit Model', 'is_active' => true]));
    $second = IdempotencyKey::run('audit-81', 'audit.model', fn () => throw new RuntimeException('must not run'));

    expect($second)->toBeInstanceOf(Brand::class)->and($second->id)->toBe($first->id);
});

test('IDEM-082 nested array içindeki Eloquent modeller retryda yeniden yüklenir', function () {
    [$company, $period] = $this->createCompanyWithPeriod('AUDI82');
    $user = $this->createUserWithPeriodAccess($company, $period, 'Yönetici');
    $this->loginToPeriod($user, $company, $period);

    $a = Brand::query()->create(['name' => 'Nested A', 'is_active' => true]);
    $b = Brand::query()->create(['name' => 'Nested B', 'is_active' => true]);

    IdempotencyKey::run('audit-82', 'audit.nested', fn () => ['items' => [$a, ['brand' => $b]]]);
    $retry = IdempotencyKey::run('audit-82', 'audit.nested', fn () => throw new RuntimeException('must not run'));

    expect($retry['items'][0])->toBeInstanceOf(Brand::class)
        ->and($retry['items'][1]['brand'])->toBeInstanceOf(Brand::class)
        ->and($retry['items'][0]->id)->toBe($a->id)
        ->and($retry['items'][1]['brand']->id)->toBe($b->id);
});

test('IDEM-083 finance transfer shape source target nested model tipini korur', function () {
    [$company, $period] = $this->createCompanyWithPeriod('AUDI83');
    $user = $this->createUserWithPeriodAccess($company, $period, 'Yönetici');
    $this->loginToPeriod($user, $company, $period);

    $source = Brand::query()->create(['name' => 'Source marker', 'is_active' => true]);
    $target = Brand::query()->create(['name' => 'Target marker', 'is_active' => true]);

    IdempotencyKey::run('audit-83', 'finance-transfer.create', fn () => ['source' => $source, 'target' => $target]);
    $retry = IdempotencyKey::run('audit-83', 'finance-transfer.create', fn () => throw new RuntimeException('must not run'));

    expect($retry)->toHaveKeys(['source', 'target'])
        ->and($retry['source'])->toBeInstanceOf(Brand::class)
        ->and($retry['target'])->toBeInstanceOf(Brand::class);
});

test('IDEM-084 scalar sonuçlar kayıpsız döner', function () {
    foreach ([true, false, 0, 7, 'text', null, 12.5] as $index => $value) {
        $key = 'audit-84-'.$index;
        $first = IdempotencyKey::runMaster($key, 'audit.scalar', fn () => $value);
        $retry = IdempotencyKey::runMaster($key, 'audit.scalar', fn () => 'wrong');
        expect($retry)->toBe($first);
    }
});

test('IDEM-085 associative array sonucu kayıpsız döner', function () {
    $value = ['ok' => true, 'nested' => ['count' => 3, 'code' => 'X']];
    IdempotencyKey::runMaster('audit-85', 'audit.array', fn () => $value);

    expect(IdempotencyKey::runMaster('audit-85', 'audit.array', fn () => []))->toEqual($value);
});

test('IDEM-086 legacy type value kayıtları geriye dönük okunabilir', function () {
    DB::connection('master')->table('idempotency_keys')->insert([
        'key' => 'audit-86',
        'action' => 'audit.legacy',
        'status' => 'done',
        'result' => json_encode(['type' => 'value', 'value' => ['legacy' => true]]),
        'completed_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(IdempotencyKey::runMaster('audit-86', 'audit.legacy', fn () => ['legacy' => false]))
        ->toBe(['legacy' => true]);
});

test('IDEM-087 aynı period idempotency key farklı fiziksel periodlarda bağımsızdır', function () {
    [$companyA, $periodA] = $this->createCompanyWithPeriod('AUDI87A');
    $userA = $this->createUserWithPeriodAccess($companyA, $periodA, 'Yönetici');
    $this->loginToPeriod($userA, $companyA, $periodA);
    expect(IdempotencyKey::run('audit-87', 'audit.scope', fn () => 'A'))->toBe('A');

    [$companyB, $periodB] = $this->createCompanyWithPeriod('AUDI87B');
    $userB = $this->createUserWithPeriodAccess($companyB, $periodB, 'Yönetici');
    $this->loginToPeriod($userB, $companyB, $periodB);
    expect(IdempotencyKey::run('audit-87', 'audit.scope', fn () => 'B'))->toBe('B');
});

test('IDEM-088 document posting idempotency guard taşır', function () {
    expect(AuditSource::read('app/Actions/Documents/PostDocument.php'))->toContain('IdempotencyKey::run(');
});

test('IDEM-089 finance payment collection mutations idempotency guard taşır', function () {
    $combined = AuditSource::read('app/Actions/Finance/PostCollection.php')
        .AuditSource::read('app/Actions/Purchases/PostPayment.php');

    expect($combined)->toContain('IdempotencyKey::run(');
});

test('IDEM-090 stock reservation retry duplicate reservation üretmeyecek guard taşır', function () {
    expect(AuditSource::read('app/Actions/Stock/ReserveStock.php'))->toContain('IdempotencyKey::run(');
});

test('IDEM-091 channel inbound processing external registry claimi kullanır', function () {
    expect(AuditSource::read('app/Actions/Channels/ProcessChannelInboundEvent.php'))
        ->toContain('ChannelExternalEventRegistryService');
});

test('IDEM-092 channel cancellation idempotency guard taşır', function () {
    expect(AuditSource::read('app/Actions/Channels/ImportChannelCancellation.php'))
        ->toContain('IdempotencyKey::run(');
});

test('IDEM-093 channel return idempotency veya external registry scope taşır', function () {
    $source = AuditSource::read('app/Actions/Channels/ImportChannelReturn.php');
    expect($source)->toMatch('/IdempotencyKey::run\(|external|snapshot/i');
});

test('IDEM-094 period carry master idempotency ile korunur', function () {
    expect(AuditSource::read('app/Actions/Periods/CarryPeriod.php'))->toContain('IdempotencyKey::runMaster(');
});

<?php

use App\Actions\Contacts\SaveContact;
use App\Actions\Periods\ClosePeriod;
use App\Actions\Periods\CreatePeriod;
use App\Actions\Periods\ReopenPeriod;
use App\Actions\Products\SaveProduct;
use App\Actions\Stock\ReceiveToQuarantine;
use App\Actions\Stock\RecordStockMovement;
use App\Actions\Stock\ReleaseQuarantine;
use App\Actions\Stock\ReserveStock;
use App\DataObjects\QuarantineReceiptData;
use App\DataObjects\StockMovementData;
use App\Exceptions\IdempotencyInProgressException;
use App\Livewire\Components\PeriodSwitcher;
use App\Livewire\Concerns\WithIdempotentMutations;
use App\Livewire\Pages\Catalog\BrandForm;
use App\Livewire\Products\ProductImages;
use App\Livewire\Shell\CompanySwitcher;
use App\Models\Company;
use App\Models\Period;
use App\Models\Period\Brand;
use App\Models\Period\Contact;
use App\Models\Period\Location;
use App\Models\Period\Product;
use App\Models\Period\StockBalance;
use App\Models\Period\StockReservation;
use App\Models\Period\Unit;
use App\Support\Concurrency\IdempotencyKey;
use App\Support\Period\PeriodContext;
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

it('aynı idempotency key farklı action scopeunda kullanılamaz', function () {
    IdempotencyKey::runMaster(
        'master-action-collision-key',
        'test.master.first',
        fn (): string => 'first',
    );

    expect(fn () => IdempotencyKey::runMaster(
        'master-action-collision-key',
        'test.master.second',
        fn (): string => 'second',
    ))->toThrow(RuntimeException::class, 'Aynı idempotency key farklı action için kullanılamaz.');
});

it('processing durumundaki aynı logical request paralel tekrar olarak reddedilir', function () {
    DB::connection('master')->table('idempotency_keys')->insert([
        'key' => 'master-processing-key',
        'action' => 'test.master.processing',
        'status' => 'processing',
        'result' => null,
        'completed_at' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(fn () => IdempotencyKey::runMaster(
        'master-processing-key',
        'test.master.processing',
        fn (): string => 'should-not-run',
    ))->toThrow(IdempotencyInProgressException::class, 'İşlem aynı istek anahtarıyla halen sürüyor.');
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

it('mutation wrapper snapshot öncesi seed edilmemiş keyi sessizce üretmez', function () {
    $subject = new class
    {
        use WithIdempotentMutations;

        public function runWithoutSeed(): mixed
        {
            return $this->runMasterMutation('missing', fn (): bool => true);
        }
    };

    expect(fn () => $subject->runWithoutSeed())
        ->toThrow(LogicException::class, 'mutation key Livewire snapshot oluşturulmadan önce');
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

it('görsel ve şirket dönem switcher mutation keylerini ilk Livewire snapshotında hazırlar', function () {
    [$company, $period] = $this->createCompanyWithPeriod('IDEMSNAP');
    $user = $this->createUserWithPeriodAccess($company, $period, 'Yönetici');
    $this->loginToPeriod($user, $company, $period);
    $product = $this->createTestProduct(['code' => 'IDEM-IMG']);

    $imageKeys = Livewire::test(ProductImages::class, ['product' => $product])->get('mutationKeys');
    $periodKeys = Livewire::test(PeriodSwitcher::class)->get('mutationKeys');
    $companyKeys = Livewire::test(CompanySwitcher::class)->get('mutationKeys');

    expect($imageKeys)->toHaveKeys(['upload', 'delete', 'reorder', 'move'])
        ->and($periodKeys)->toHaveKeys(['selectCompany', 'selectPeriod'])
        ->and($companyKeys)->toHaveKey('selectCompany');

    foreach (array_merge(array_values($imageKeys), array_values($periodKeys), array_values($companyKeys)) as $key) {
        expect($key)->toBeString()->not->toBe('');
    }
});

it('quarantine release aynı request key ile retry edildiğinde tek kez uygulanır', function () {
    [$company, $period] = $this->createCompanyWithPeriod('IDEMQUAR');
    $user = $this->createUserWithPeriodAccess($company, $period, 'Yönetici');
    $this->loginToPeriod($user, $company, $period);

    $product = $this->createTestProduct(['code' => 'IDEM-Q']);
    $location = Location::query()->create([
        'code' => 'IDEM-Q-WH',
        'name' => 'Idempotency Karantina Depo',
        'kind' => 'warehouse',
        'is_default' => false,
        'is_active' => true,
    ]);

    $entry = app(ReceiveToQuarantine::class)->handle(new QuarantineReceiptData(
        productId: $product->id,
        locationId: $location->id,
        movementDate: '2026-10-01',
        quantity: '5.000',
        unitCost: '10.0000',
        sourceDocumentType: 'sales_return',
        sourceDocumentId: 901,
        sourceLineId: 1,
        actorUserId: $user->id,
        actorUserName: $user->name,
    ), 'quarantine-receive-seed-key');

    $first = app(ReleaseQuarantine::class)->handle(
        $entry->id,
        '2.000',
        'quarantine-release-retry-key',
    );
    $second = app(ReleaseQuarantine::class)->handle(
        $entry->id,
        '2.000',
        'quarantine-release-retry-key',
    );

    $balance = StockBalance::query()
        ->where('product_id', $product->id)
        ->where('location_id', $location->id)
        ->firstOrFail();

    expect($first->id)->toBe($second->id)
        ->and((string) $second->released_quantity)->toBe('2.000')
        ->and((string) $balance->quantity)->toBe('5.000')
        ->and((string) $balance->quarantine)->toBe('3.000');
});

it('reservation create aynı request key ile retry edildiğinde tek rezervasyon üretir', function () {
    [$company, $period] = $this->createCompanyWithPeriod('IDEMRES');
    $user = $this->createUserWithPeriodAccess($company, $period, 'Yönetici');
    $this->loginToPeriod($user, $company, $period);

    $product = $this->createTestProduct(['code' => 'IDEM-R']);
    $location = Location::query()->create([
        'code' => 'IDEM-R-WH',
        'name' => 'Idempotency Rezervasyon Depo',
        'kind' => 'warehouse',
        'is_default' => false,
        'is_active' => true,
    ]);

    app(RecordStockMovement::class)->handle(new StockMovementData(
        productId: $product->id,
        locationId: $location->id,
        movementDate: '2026-10-01',
        direction: 'in',
        reason: 'purchase',
        quantity: '5.000',
        unitCost: '10.0000',
        updatesAverage: true,
        actorUserId: $user->id,
        actorUserName: $user->name,
    ));

    $arguments = [
        'productId' => $product->id,
        'requestedQuantity' => '3.000',
        'orderedLocationIds' => [$location->id],
        'documentType' => 'sales_order',
        'documentId' => 902,
        'documentLineId' => 1,
        'idempotencyKey' => 'reservation-create-retry-key',
        'actorUserId' => $user->id,
        'actorUserName' => $user->name,
    ];

    $first = app(ReserveStock::class)->handle(...$arguments);
    $second = app(ReserveStock::class)->handle(...$arguments);

    $balance = StockBalance::query()
        ->where('product_id', $product->id)
        ->where('location_id', $location->id)
        ->firstOrFail();

    expect($second->reservationIds)->toBe($first->reservationIds)
        ->and(StockReservation::query()->where('document_id', 902)->count())->toBe(1)
        ->and((string) $balance->reserved)->toBe('3.000');
});

it('core contact ve product save callbackleri aynı request key retryında çoğalmaz', function () {
    [$company, $period] = $this->createCompanyWithPeriod('IDEMCORE');
    $user = $this->createUserWithPeriodAccess($company, $period, 'Yönetici');
    $this->loginToPeriod($user, $company, $period);

    $contactPayload = [
        'title' => 'Idempotent Cari',
        'type' => 'legal',
        'risk_limit' => '0',
        'discount_rate' => '0',
        'category_ids' => [],
        'is_active' => true,
    ];

    $firstContact = IdempotencyKey::run(
        'contact-save-retry-key',
        'test.contact.save',
        fn () => app(SaveContact::class)->handle($contactPayload),
    );
    $secondContact = IdempotencyKey::run(
        'contact-save-retry-key',
        'test.contact.save',
        fn () => app(SaveContact::class)->handle($contactPayload),
    );

    $unit = Unit::query()->where('code', 'ADET')->firstOrFail();
    $productPayload = [
        'code' => 'IDEM-P',
        'name' => 'Idempotent Ürün',
        'unit_id' => $unit->id,
        'vat_rate' => '20',
        'list_price' => '100',
        'price_vat_included' => false,
        'currency' => 'TRY',
        'kind' => 'normal',
        'allow_negative_stock' => false,
        'min_stock' => '0',
        'channel_stock_mode' => 'stock',
        'is_active' => true,
    ];

    $firstProduct = IdempotencyKey::run(
        'product-save-retry-key',
        'test.product.save',
        fn () => app(SaveProduct::class)->handle($productPayload),
    );
    $secondProduct = IdempotencyKey::run(
        'product-save-retry-key',
        'test.product.save',
        fn () => app(SaveProduct::class)->handle($productPayload),
    );

    expect($secondContact->id)->toBe($firstContact->id)
        ->and(Contact::query()->where('title', 'Idempotent Cari')->count())->toBe(1)
        ->and($secondProduct->id)->toBe($firstProduct->id)
        ->and(Product::query()->where('code', 'IDEM-P')->count())->toBe(1);
});

it('period create aynı master request key retryında fiziksel dönemi ikinci kez oluşturmaz', function () {
    $suffix = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
    $dbPrefix = 'TST_IDEM_'.$suffix;
    $databaseName = $dbPrefix.'_2027';

    $company = Company::factory()->create([
        'code' => 'IDEM'.$suffix,
        'name' => 'Idempotent Period Create',
        'db_prefix' => $dbPrefix,
    ]);

    try {
        $first = IdempotencyKey::runMaster(
            'period-create-'.$suffix,
            'test.period.create',
            fn () => app(CreatePeriod::class)->handle($company, 2027),
        );
        $second = IdempotencyKey::runMaster(
            'period-create-'.$suffix,
            'test.period.create',
            fn () => app(CreatePeriod::class)->handle($company, 2027),
        );

        expect($second)->toBeInstanceOf(Period::class)
            ->and($second->id)->toBe($first->id)
            ->and(Period::query()->where('database_name', $databaseName)->count())->toBe(1);
    } finally {
        PeriodContext::clear();
        DB::purge('period');
        Period::query()->where('database_name', $databaseName)->delete();
        DB::connection('master')->statement(
            sprintf('DROP DATABASE IF EXISTS "%s" WITH (FORCE)', $databaseName),
        );
    }
});

it('period close ve reopen aynı master request key retryında tek logical mutation olarak kalır', function () {
    [$company, $period] = $this->createCompanyWithPeriod('IDEMSTATE');
    $user = $this->createUserWithPeriodAccess($company, $period, 'Yönetici');
    $this->loginToPeriod($user, $company, $period);

    $firstClose = IdempotencyKey::runMaster(
        'period-close-retry-key',
        'test.period.close',
        fn () => app(ClosePeriod::class)->handle($period->fresh()),
    );
    $secondClose = IdempotencyKey::runMaster(
        'period-close-retry-key',
        'test.period.close',
        fn () => app(ClosePeriod::class)->handle($period->fresh()),
    );

    expect($secondClose->id)->toBe($firstClose->id)
        ->and($period->fresh()->status)->toBe('closed');

    $firstReopen = IdempotencyKey::runMaster(
        'period-reopen-retry-key',
        'test.period.reopen',
        fn () => app(ReopenPeriod::class)->handle($period->fresh(), 'Regression retry'),
    );
    $secondReopen = IdempotencyKey::runMaster(
        'period-reopen-retry-key',
        'test.period.reopen',
        fn () => app(ReopenPeriod::class)->handle($period->fresh(), 'Regression retry'),
    );

    expect($secondReopen->id)->toBe($firstReopen->id)
        ->and($period->fresh()->status)->toBe('active');
});

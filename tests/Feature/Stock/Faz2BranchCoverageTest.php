<?php

use App\Actions\Stock\ConsumeReservation;
use App\Actions\Stock\RecordStockMovement;
use App\Actions\Stock\ReleaseReservation;
use App\Actions\Stock\ReserveStock;
use App\Actions\Stock\SaveTransferDraft;
use App\Actions\Stock\SaveWarehouseSlipDraft;
use App\Actions\Stock\UpdateMovingAverage;
use App\DataObjects\StockMovementData;
use App\Models\Period\Location;
use App\Models\Period\ProductCost;
use App\Models\Period\StockBalance;
use App\Models\Period\StockReservation;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

function faz2BranchWarehouse(string $code): Location
{
    return Location::query()->create([
        'code' => $code,
        'name' => $code,
        'kind' => 'warehouse',
        'is_default' => false,
        'is_active' => true,
    ]);
}

function faz2BranchMovement(int $productId, int $locationId, string $quantity, string $cost = '10.0000'): void
{
    app(RecordStockMovement::class)->handle(new StockMovementData(
        productId: $productId,
        locationId: $locationId,
        movementDate: '2026-09-01',
        direction: 'in',
        reason: 'purchase',
        quantity: $quantity,
        unitCost: $cost,
        updatesAverage: true,
        actorUserId: auth()->id(),
        actorUserName: auth()->user()?->name,
    ));
}

it('rezervasyonu kismi cozer ve tuketir, gecersiz miktarlari reddeder', function () {
    [$company, $period] = $this->createCompanyWithPeriod('RSVBRANCH');
    $admin = $this->createUserWithPeriodAccess($company, $period, 'Yönetici');
    $this->loginToPeriod($admin, $company, $period);

    $product = $this->createTestProduct(['code' => 'RSV-BR']);
    $location = faz2BranchWarehouse('RSV-BR-WH');
    faz2BranchMovement($product->id, $location->id, '20.000');

    $releaseResult = app(ReserveStock::class)->handle(
        productId: $product->id,
        requestedQuantity: '8.000',
        orderedLocationIds: [$location->id],
        documentType: 'sales_order',
        documentId: 101,
        documentLineId: 102,
        idempotencyKey: (string) Str::uuid(),
        actorUserId: $admin->id,
        actorUserName: $admin->name,
    );
    $releaseReservation = StockReservation::query()->findOrFail($releaseResult->reservationIds[0]);

    $releasedPart = app(ReleaseReservation::class)->handle(
        $releaseReservation->id,
        (string) Str::uuid(),
        '3.000',
    );

    expect($releaseReservation->refresh()->status)->toBe('active')
        ->and((string) $releaseReservation->quantity)->toBe('5.000')
        ->and($releasedPart->status)->toBe('released')
        ->and((string) $releasedPart->quantity)->toBe('3.000')
        ->and((string) StockBalance::query()
            ->where('product_id', $product->id)
            ->where('location_id', $location->id)
            ->value('reserved'))->toBe('5.000');

    expect(fn () => app(ReleaseReservation::class)->handle(
        $releaseReservation->id,
        (string) Str::uuid(),
        '0.000',
    ))->toThrow(DomainException::class, 'Rezervasyon çözme miktarı geçersiz.');

    expect(fn () => app(ReleaseReservation::class)->handle(
        $releaseReservation->id,
        (string) Str::uuid(),
        '6.000',
    ))->toThrow(DomainException::class, 'Rezervasyon çözme miktarı geçersiz.');

    app(ReleaseReservation::class)->handle(
        $releaseReservation->id,
        (string) Str::uuid(),
    );

    $consumeResult = app(ReserveStock::class)->handle(
        productId: $product->id,
        requestedQuantity: '8.000',
        orderedLocationIds: [$location->id],
        documentType: 'sales_order',
        documentId: 103,
        documentLineId: 104,
        idempotencyKey: (string) Str::uuid(),
        actorUserId: $admin->id,
        actorUserName: $admin->name,
    );
    $consumeReservation = StockReservation::query()->findOrFail($consumeResult->reservationIds[0]);

    $consumedPart = app(ConsumeReservation::class)->handle(
        $consumeReservation->id,
        (string) Str::uuid(),
        '3.000',
    );

    expect($consumeReservation->refresh()->status)->toBe('active')
        ->and((string) $consumeReservation->quantity)->toBe('5.000')
        ->and($consumedPart->status)->toBe('consumed')
        ->and((string) $consumedPart->quantity)->toBe('3.000');

    expect(fn () => app(ConsumeReservation::class)->handle(
        $consumeReservation->id,
        (string) Str::uuid(),
        '-1.000',
    ))->toThrow(DomainException::class, 'Rezervasyon tüketim miktarı geçersiz.');

    expect(fn () => app(ConsumeReservation::class)->handle(
        $consumeReservation->id,
        (string) Str::uuid(),
        '6.000',
    ))->toThrow(DomainException::class, 'Rezervasyon tüketim miktarı geçersiz.');

    app(ConsumeReservation::class)->handle(
        $consumeReservation->id,
        (string) Str::uuid(),
    );

    expect($consumeReservation->refresh()->status)->toBe('consumed')
        ->and((string) StockBalance::query()
            ->where('product_id', $product->id)
            ->where('location_id', $location->id)
            ->value('reserved'))->toBe('0.000');
});

it('transfer draft validation ve immutable durum guardlarini uygular', function () {
    [$company, $period] = $this->createCompanyWithPeriod('TRBRANCH');
    $admin = $this->createUserWithPeriodAccess($company, $period, 'Yönetici');
    $this->loginToPeriod($admin, $company, $period);

    $product = $this->createTestProduct(['code' => 'TR-BR']);
    $from = faz2BranchWarehouse('TR-BR-A');
    $to = faz2BranchWarehouse('TR-BR-B');

    expect(fn () => app(SaveTransferDraft::class)->handle([
        'from_location_id' => $from->id,
        'to_location_id' => $to->id,
        'transfer_date' => '2026-09-01',
        'lines' => [],
    ]))->toThrow(ValidationException::class);

    expect(fn () => app(SaveTransferDraft::class)->handle([
        'from_location_id' => $from->id,
        'to_location_id' => $to->id,
        'transfer_date' => '2026-09-01',
        'lines' => [['product_id' => $product->id, 'quantity' => '0.000']],
    ]))->toThrow(ValidationException::class);

    expect(fn () => app(SaveTransferDraft::class)->handle([
        'from_location_id' => $from->id,
        'to_location_id' => $to->id,
        'transfer_date' => '2026-09-01',
        'production_order_id' => 999999,
        'lines' => [['product_id' => $product->id, 'quantity' => '1.000']],
    ]))->toThrow(
        DomainException::class,
        'Production-order transfer provenance yalnız fason lokasyon sevkinde kullanılabilir.',
    );

    $draft = app(SaveTransferDraft::class)->handle([
        'from_location_id' => $from->id,
        'to_location_id' => $to->id,
        'transfer_date' => '2026-09-01',
        'lines' => [['product_id' => $product->id, 'quantity' => '1.000']],
    ]);

    $draft->forceFill(['status' => 'in_transit'])->save();

    expect(fn () => app(SaveTransferDraft::class)->handle([
        'from_location_id' => $from->id,
        'to_location_id' => $to->id,
        'transfer_date' => '2026-09-02',
        'lines' => [['product_id' => $product->id, 'quantity' => '1.000']],
    ], $draft))->toThrow(DomainException::class, 'Yalnız taslak transfer düzenlenebilir.');
});

it('ambar fisi draft validation ve immutable durum guardlarini uygular', function () {
    [$company, $period] = $this->createCompanyWithPeriod('SLIPBRANCH');
    $admin = $this->createUserWithPeriodAccess($company, $period, 'Yönetici');
    $this->loginToPeriod($admin, $company, $period);

    $product = $this->createTestProduct(['code' => 'SLIP-BR']);
    $location = faz2BranchWarehouse('SLIP-BR-WH');

    $base = [
        'location_id' => $location->id,
        'slip_date' => '2026-09-01',
        'direction' => 'in',
        'reason' => 'adjustment',
        'lines' => [[
            'product_id' => $product->id,
            'quantity' => '1.000',
            'unit_cost' => '10.0000',
            'note' => null,
        ]],
    ];

    expect(fn () => app(SaveWarehouseSlipDraft::class)->handle([
        ...$base,
        'direction' => 'bad',
    ]))->toThrow(ValidationException::class);

    expect(fn () => app(SaveWarehouseSlipDraft::class)->handle([
        ...$base,
        'reason' => 'scrap',
    ]))->toThrow(ValidationException::class);

    expect(fn () => app(SaveWarehouseSlipDraft::class)->handle([
        ...$base,
        'lines' => [],
    ]))->toThrow(ValidationException::class);

    expect(fn () => app(SaveWarehouseSlipDraft::class)->handle([
        ...$base,
        'direction' => 'out',
        'reason' => 'scrap',
    ]))->toThrow(ValidationException::class);

    expect(fn () => app(SaveWarehouseSlipDraft::class)->handle([
        ...$base,
        'lines' => [[
            'product_id' => $product->id,
            'quantity' => '1.000',
            'unit_cost' => '-1.0000',
            'note' => null,
        ]],
    ]))->toThrow(ValidationException::class);

    $draft = app(SaveWarehouseSlipDraft::class)->handle($base);
    $draft->forceFill(['status' => 'posted'])->save();

    expect(fn () => app(SaveWarehouseSlipDraft::class)->handle($base, $draft))
        ->toThrow(DomainException::class, 'Yalnız taslak ambar fişi düzenlenebilir.');
});

it('moving average value delta ve negatif stok degeri guardini uygular', function () {
    [$company, $period] = $this->createCompanyWithPeriod('COSTBRANCH');
    $admin = $this->createUserWithPeriodAccess($company, $period, 'Yönetici');
    $this->loginToPeriod($admin, $company, $period);

    $product = $this->createTestProduct(['code' => 'COST-BR']);
    $location = faz2BranchWarehouse('COST-BR-WH');
    faz2BranchMovement($product->id, $location->id, '10.000', '100.0000');

    $costs = app(UpdateMovingAverage::class);

    expect($costs->applyValueDelta(
        $product->id,
        '100.0000',
        '120.0000',
        '2026-09-02',
    ))->toBe('110.0000');

    $cost = ProductCost::query()->where('product_id', $product->id)->firstOrFail();

    expect((string) $cost->last_purchase_price)->toBe('120.0000')
        ->and($cost->last_purchase_at?->toDateString())->toBe('2026-09-02');

    expect(fn () => $costs->applyValueDelta(
        $product->id,
        '-2000.0000',
    ))->toThrow(DomainException::class, 'Maliyet değer düzeltmesi stok değerini negatife düşüremez.');

    $emptyProduct = $this->createTestProduct(['code' => 'COST-ZERO']);

    expect($costs->applyValueDelta(
        $emptyProduct->id,
        '50.0000',
        '70.0000',
    ))->toBe('0.0000');

    $emptyCost = ProductCost::query()->where('product_id', $emptyProduct->id)->firstOrFail();

    expect((string) $emptyCost->last_purchase_price)->toBe('70.0000')
        ->and($emptyCost->last_purchase_at)->toBeNull();
});

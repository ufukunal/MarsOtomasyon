<?php

use App\Actions\ReferenceData\SaveUnitConversion;
use App\Actions\Stock\ReceiveToQuarantine;
use App\Actions\Stock\RecordStockMovement;
use App\Actions\Stock\ReserveStock;
use App\DataObjects\QuarantineReceiptData;
use App\DataObjects\StockMovementData;
use App\Models\Period\Location;
use App\Models\Period\StockBalance;
use App\Models\Period\Unit;
use App\Support\Integrity\Checks\CostIntegrityCheck;
use App\Support\Integrity\Checks\QuarantineBalanceCheck;
use App\Support\Integrity\Checks\ReservationBalanceCheck;
use App\Support\Integrity\Checks\StockBalanceCheck;
use App\Support\Integrity\Checks\UnitIntegrityCheck;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function faz2IntegrityWarehouse(string $code): Location
{
    return Location::query()->create([
        'code' => $code,
        'name' => $code.' Depo',
        'kind' => 'warehouse',
        'is_default' => false,
        'is_active' => true,
    ]);
}

it('Faz 2 integrity kontrollerinin tamamı temiz veride fark bulmaz', function () {
    [$company, $period] = $this->createCompanyWithPeriod('INTOK');
    $admin = $this->createUserWithPeriodAccess($company, $period, 'Yönetici');
    $this->loginToPeriod($admin, $company, $period);

    $stockProduct = $this->createTestProduct(['code' => 'INT-STK']);
    $reservedProduct = $this->createTestProduct(['code' => 'INT-RSV']);
    $quarantineProduct = $this->createTestProduct(['code' => 'INT-QR']);
    $location = faz2IntegrityWarehouse('INT-A');

    foreach ([$stockProduct, $reservedProduct, $quarantineProduct] as $product) {
        app(RecordStockMovement::class)->handle(new StockMovementData(
            productId: $product->id,
            locationId: $location->id,
            movementDate: '2026-11-01',
            direction: 'in',
            reason: 'purchase',
            quantity: '10.000',
            unitCost: '100.0000',
            updatesAverage: true,
            actorUserId: $admin->id,
            actorUserName: $admin->name,
        ));
    }

    app(ReserveStock::class)->handle(
        productId: $reservedProduct->id,
        requestedQuantity: '3.000',
        orderedLocationIds: [$location->id],
        documentType: 'sales_order',
        documentId: 100,
        documentLineId: 101,
        idempotencyKey: (string) Str::uuid(),
        actorUserId: $admin->id,
        actorUserName: $admin->name,
    );

    app(ReceiveToQuarantine::class)->handle(new QuarantineReceiptData(
        productId: $quarantineProduct->id,
        locationId: $location->id,
        movementDate: '2026-11-02',
        quantity: '2.000',
        unitCost: '100.0000',
        sourceDocumentType: 'sales_return',
        sourceDocumentId: 200,
        sourceLineId: 201,
        actorUserId: $admin->id,
        actorUserName: $admin->name,
    ), (string) Str::uuid());

    $piece = Unit::query()->where('code', 'ADET')->firstOrFail();
    $box = Unit::query()->where('code', 'KUTU')->firstOrFail();

    app(SaveUnitConversion::class)->handle([
        'from_unit_id' => $box->id,
        'to_unit_id' => $piece->id,
        'factor' => '6',
    ]);

    $checks = [
        app(StockBalanceCheck::class),
        app(CostIntegrityCheck::class),
        app(ReservationBalanceCheck::class),
        app(QuarantineBalanceCheck::class),
        app(UnitIntegrityCheck::class),
    ];

    foreach ($checks as $check) {
        expect($check->run()->mismatchCount())
            ->toBe(0, $check->name().' integrity farkı üretmemeli.');
    }

    foreach ([
        'integrity:stock',
        'integrity:costs',
        'integrity:reservations',
        'integrity:quarantine',
        'integrity:units',
    ] as $command) {
        expect(Artisan::call($command))->toBe(0, "{$command} başarısız.");
    }
});

it('integrity kontrolleri türetilmiş stok maliyet rezerv ve karantina bozulmalarını raporlar', function () {
    [$company, $period] = $this->createCompanyWithPeriod('INTBAD');
    $admin = $this->createUserWithPeriodAccess($company, $period, 'Yönetici');
    $this->loginToPeriod($admin, $company, $period);

    $product = $this->createTestProduct(['code' => 'INT-BAD']);
    $location = faz2IntegrityWarehouse('INT-B');

    app(RecordStockMovement::class)->handle(new StockMovementData(
        productId: $product->id,
        locationId: $location->id,
        movementDate: '2026-11-01',
        direction: 'in',
        reason: 'purchase',
        quantity: '10.000',
        unitCost: '50.0000',
        updatesAverage: true,
        actorUserId: $admin->id,
        actorUserName: $admin->name,
    ));

    $reservation = app(ReserveStock::class)->handle(
        productId: $product->id,
        requestedQuantity: '2.000',
        orderedLocationIds: [$location->id],
        documentType: 'sales_order',
        documentId: 1,
        documentLineId: 1,
        idempotencyKey: (string) Str::uuid(),
        actorUserId: $admin->id,
        actorUserName: $admin->name,
    );

    app(ReceiveToQuarantine::class)->handle(new QuarantineReceiptData(
        productId: $product->id,
        locationId: $location->id,
        movementDate: '2026-11-02',
        quantity: '1.000',
        unitCost: '50.0000',
        sourceDocumentType: 'sales_return',
        sourceDocumentId: 2,
        sourceLineId: 2,
        actorUserId: $admin->id,
        actorUserName: $admin->name,
    ), (string) Str::uuid());

    $balance = StockBalance::query()
        ->where('product_id', $product->id)
        ->where('location_id', $location->id)
        ->firstOrFail();

    DB::connection('period')->table('stock_balances')
        ->where('id', $balance->id)
        ->update([
            'quantity' => '99.000',
            'reserved' => '9.000',
            'quarantine' => '8.000',
        ]);
    DB::connection('period')->table('product_costs')
        ->where('product_id', $product->id)
        ->update(['moving_average' => '999.0000']);

    expect(app(StockBalanceCheck::class)->run()->mismatchCount())->toBeGreaterThan(0)
        ->and(app(CostIntegrityCheck::class)->run()->mismatchCount())->toBeGreaterThan(0)
        ->and(app(ReservationBalanceCheck::class)->run()->mismatchCount())->toBeGreaterThan(0)
        ->and(app(QuarantineBalanceCheck::class)->run()->mismatchCount())->toBeGreaterThan(0)
        ->and($reservation->reservationIds)->not->toBeEmpty();

    DB::connection('period')->table('stock_balances')
        ->where('id', $balance->id)
        ->update([
            'quantity' => '11.000',
            'reserved' => '2.000',
            'quarantine' => '1.000',
        ]);
    DB::connection('period')->table('product_costs')
        ->where('product_id', $product->id)
        ->update(['moving_average' => '50.0000']);

    expect(app(StockBalanceCheck::class)->run()->mismatchCount())->toBe(0)
        ->and(app(CostIntegrityCheck::class)->run()->mismatchCount())->toBe(0)
        ->and(app(ReservationBalanceCheck::class)->run()->mismatchCount())->toBe(0)
        ->and(app(QuarantineBalanceCheck::class)->run()->mismatchCount())->toBe(0);
});

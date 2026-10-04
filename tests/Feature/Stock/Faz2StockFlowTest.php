<?php

use App\Actions\Stock\CancelTransfer;
use App\Actions\Stock\CheckPurchaseCostDeviation;
use App\Actions\Stock\ConsumeReservation;
use App\Actions\Stock\ImportOpeningStock;
use App\Actions\Stock\PostStockCount;
use App\Actions\Stock\PostWarehouseSlip;
use App\Actions\Stock\ReceiveToQuarantine;
use App\Actions\Stock\ReceiveTransfer;
use App\Actions\Stock\RecordStockMovement;
use App\Actions\Stock\ReleaseQuarantine;
use App\Actions\Stock\ReleaseReservation;
use App\Actions\Stock\ReserveStock;
use App\Actions\Stock\ReverseWarehouseSlip;
use App\Actions\Stock\ReviewStockCount;
use App\Actions\Stock\SaveStockCountDraft;
use App\Actions\Stock\SaveStockCountLine;
use App\Actions\Stock\SaveTransferDraft;
use App\Actions\Stock\SaveWarehouseSlipDraft;
use App\Actions\Stock\ScrapQuarantine;
use App\Actions\Stock\SendTransfer;
use App\Actions\Stock\StartStockCount;
use App\DataObjects\QuarantineReceiptData;
use App\DataObjects\StockMovementData;
use App\Exceptions\NegativeStockException;
use App\Exceptions\PeriodClosedException;
use App\Models\Period\Location;
use App\Models\Period\ProductCost;
use App\Models\Period\StockBalance;
use App\Models\Period\StockMovement;
use App\Models\Period\StockReservation;
use App\Models\Period\WarehouseSlip;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

function faz2Warehouse(string $code, string $name = 'Test Depo'): Location
{
    return Location::query()->create([
        'code' => $code,
        'name' => $name,
        'kind' => 'warehouse',
        'is_default' => false,
        'is_active' => true,
    ]);
}

function faz2Movement(
    int $productId,
    int $locationId,
    string $date,
    string $direction,
    string $reason,
    string $quantity,
    ?string $unitCost = null,
    bool $updatesAverage = false,
): StockMovement {
    return app(RecordStockMovement::class)->handle(new StockMovementData(
        productId: $productId,
        locationId: $locationId,
        movementDate: $date,
        direction: $direction,
        reason: $reason,
        quantity: $quantity,
        unitCost: $unitCost,
        updatesAverage: $updatesAverage,
        actorUserId: auth()->id(),
        actorUserName: auth()->user()?->name,
    ));
}

it('stok hareketi ve hareketli ortalamayı BCMath sözleşmesiyle korur', function () {
    [$company, $period] = $this->createCompanyWithPeriod('STOCKFLOW');
    $admin = $this->createUserWithPeriodAccess($company, $period, 'Yönetici');
    $this->loginToPeriod($admin, $company, $period);

    $product = $this->createTestProduct(['code' => 'STK-1']);
    $location = faz2Warehouse('STK-A');

    faz2Movement($product->id, $location->id, '2026-03-01', 'in', 'purchase', '10', '100', true);
    faz2Movement($product->id, $location->id, '2026-03-02', 'in', 'purchase', '10', '200', true);
    $out = faz2Movement($product->id, $location->id, '2026-03-03', 'out', 'sale', '5');

    $balance = StockBalance::query()
        ->where('product_id', $product->id)
        ->where('location_id', $location->id)
        ->firstOrFail();
    $cost = ProductCost::query()->where('product_id', $product->id)->firstOrFail();

    expect((string) $balance->quantity)->toBe('15.000')
        ->and((string) $cost->moving_average)->toBe('150.0000')
        ->and((string) $out->unit_cost)->toBe('150.0000')
        ->and((string) $out->total_cost)->toBe('750.0000')
        ->and((string) $out->balance_after)->toBe('15.000')
        ->and((string) $out->avg_cost_after)->toBe('150.0000');

    $deviation = app(CheckPurchaseCostDeviation::class);

    expect($deviation->handle($product->id, '180.0000'))->toBeNull();

    $warning = $deviation->handle($product->id, '195.0000');
    expect($warning)->not->toBeNull()
        ->and($warning?->deviationPercent)->toBe('30.0000');

    $auditBefore = DB::connection('period')->table('activity_log')->count();
    $deviation->handle($product->id, '195.0000', true);

    expect(DB::connection('period')->table('activity_log')->count())->toBe($auditBefore + 1);
});

it('negatif stok ve kapalı ay kurallarını uygular', function () {
    [$company, $period] = $this->createCompanyWithPeriod('STOCKGUARD');
    $admin = $this->createUserWithPeriodAccess($company, $period, 'Yönetici');
    $this->loginToPeriod($admin, $company, $period);

    $location = faz2Warehouse('GUARD-A');
    $blocked = $this->createTestProduct(['code' => 'NEG-NO']);

    expect(fn () => faz2Movement(
        $blocked->id,
        $location->id,
        '2026-04-01',
        'out',
        'sale',
        '1',
    ))->toThrow(NegativeStockException::class);

    $allowed = $this->createTestProduct([
        'code' => 'NEG-YES',
        'allow_negative_stock' => true,
    ]);

    faz2Movement($allowed->id, $location->id, '2026-04-01', 'out', 'sale', '1');

    expect((string) StockBalance::query()
        ->where('product_id', $allowed->id)
        ->where('location_id', $location->id)
        ->firstOrFail()
        ->quantity)->toBe('-1.000');

    DB::connection('period')->table('posting_periods')->updateOrInsert(
        ['year' => 2026, 'month' => 5],
        [
            'status' => 'closed',
            'closed_by' => $admin->id,
            'closed_by_name' => $admin->name,
            'closed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ],
    );

    expect(fn () => faz2Movement(
        $blocked->id,
        $location->id,
        '2026-05-10',
        'in',
        'purchase',
        '1',
        '10',
        true,
    ))->toThrow(PeriodClosedException::class);
});

it('transfer maliyet snapshotını taşır ve kısmi teslimi tamamlar', function () {
    [$company, $period] = $this->createCompanyWithPeriod('TRANSFER');
    $admin = $this->createUserWithPeriodAccess($company, $period, 'Yönetici');
    $this->loginToPeriod($admin, $company, $period);

    $product = $this->createTestProduct(['code' => 'TR-P']);
    $source = faz2Warehouse('TR-A', 'Kaynak');
    $target = faz2Warehouse('TR-B', 'Hedef');

    faz2Movement($product->id, $source->id, '2026-03-01', 'in', 'purchase', '10', '100', true);

    $transfer = app(SaveTransferDraft::class)->handle([
        'from_location_id' => $source->id,
        'to_location_id' => $target->id,
        'transfer_date' => '2026-03-02',
        'note' => null,
        'lines' => [[
            'product_id' => $product->id,
            'quantity' => '10.000',
        ]],
    ]);

    $transfer = app(SendTransfer::class)->handle($transfer->id, (string) Str::uuid());

    expect($transfer->status)->toBe('in_transit')
        ->and((string) StockBalance::query()
            ->where('product_id', $product->id)
            ->where('location_id', $source->id)
            ->firstOrFail()
            ->quantity)->toBe('0.000')
        ->and(StockBalance::query()
            ->where('product_id', $product->id)
            ->where('location_id', $target->id)
            ->value('quantity'))->toBeNull();

    $sourceOut = StockMovement::query()
        ->where('document_type', 'transfer')
        ->where('document_id', $transfer->id)
        ->where('direction', 'out')
        ->firstOrFail();

    expect((string) $sourceOut->unit_cost)->toBe('100.0000');

    faz2Movement($product->id, $source->id, '2026-03-03', 'in', 'purchase', '10', '200', true);

    $lineId = $transfer->lines()->firstOrFail()->id;
    $transfer = app(ReceiveTransfer::class)->handle(
        $transfer->id,
        [$lineId => '4.000'],
        (string) Str::uuid(),
    );

    expect($transfer->status)->toBe('partially_received')
        ->and((string) $transfer->lines()->firstOrFail()->received_quantity)->toBe('4.000');

    $targetIn = StockMovement::query()
        ->where('document_type', 'transfer')
        ->where('document_id', $transfer->id)
        ->where('direction', 'in')
        ->latest('id')
        ->firstOrFail();

    expect((string) $targetIn->unit_cost)->toBe('100.0000')
        ->and((string) ProductCost::query()->where('product_id', $product->id)->value('moving_average'))
        ->toBe('200.0000');

    $transfer = app(ReceiveTransfer::class)->handle(
        $transfer->id,
        [$lineId => '6.000'],
        (string) Str::uuid(),
    );

    expect($transfer->status)->toBe('received')
        ->and((string) StockBalance::query()
            ->where('product_id', $product->id)
            ->where('location_id', $target->id)
            ->firstOrFail()
            ->quantity)->toBe('10.000');

    expect(fn () => app(SaveTransferDraft::class)->handle([
        'from_location_id' => $source->id,
        'to_location_id' => $source->id,
        'transfer_date' => '2026-03-04',
        'lines' => [['product_id' => $product->id, 'quantity' => '1.000']],
    ]))->toThrow(ValidationException::class);
});

it('transit transfer iptalinde yalnız kalan miktarı kaynağa ters hareketle döndürür', function () {
    [$company, $period] = $this->createCompanyWithPeriod('TRCANCEL');
    $admin = $this->createUserWithPeriodAccess($company, $period, 'Yönetici');
    $this->loginToPeriod($admin, $company, $period);

    $product = $this->createTestProduct(['code' => 'TR-C']);
    $source = faz2Warehouse('TC-A');
    $target = faz2Warehouse('TC-B');

    faz2Movement($product->id, $source->id, '2026-02-01', 'in', 'purchase', '10', '50', true);

    $transfer = app(SaveTransferDraft::class)->handle([
        'from_location_id' => $source->id,
        'to_location_id' => $target->id,
        'transfer_date' => '2026-02-02',
        'lines' => [['product_id' => $product->id, 'quantity' => '10.000']],
    ]);
    $transfer = app(SendTransfer::class)->handle($transfer->id, (string) Str::uuid());
    $lineId = $transfer->lines()->firstOrFail()->id;
    $transfer = app(ReceiveTransfer::class)->handle(
        $transfer->id,
        [$lineId => '4.000'],
        (string) Str::uuid(),
    );

    $transfer = app(CancelTransfer::class)->handle($transfer->id, (string) Str::uuid());

    expect($transfer->status)->toBe('cancelled')
        ->and((string) StockBalance::query()->where('product_id', $product->id)->where('location_id', $source->id)->value('quantity'))->toBe('6.000')
        ->and((string) StockBalance::query()->where('product_id', $product->id)->where('location_id', $target->id)->value('quantity'))->toBe('4.000');
});

it('ambar fişini kesinleştirir ve ters fişle geri alır', function () {
    [$company, $period] = $this->createCompanyWithPeriod('SLIP');
    $admin = $this->createUserWithPeriodAccess($company, $period, 'Yönetici');
    $this->loginToPeriod($admin, $company, $period);

    $product = $this->createTestProduct(['code' => 'SLP-1']);
    $location = faz2Warehouse('SLIP-A');

    $slip = app(SaveWarehouseSlipDraft::class)->handle([
        'location_id' => $location->id,
        'slip_date' => '2026-06-01',
        'direction' => 'in',
        'reason' => 'adjustment',
        'note' => null,
        'lines' => [[
            'product_id' => $product->id,
            'quantity' => '5.000',
            'unit_cost' => '25.0000',
            'note' => null,
        ]],
    ]);

    $slip = app(PostWarehouseSlip::class)->handle($slip->id, (string) Str::uuid());

    expect($slip->status)->toBe('posted')
        ->and((string) StockBalance::query()
            ->where('product_id', $product->id)
            ->where('location_id', $location->id)
            ->value('quantity'))->toBe('5.000');

    $slip = app(ReverseWarehouseSlip::class)->handle($slip->id, (string) Str::uuid());

    expect($slip->status)->toBe('cancelled')
        ->and((string) StockBalance::query()
            ->where('product_id', $product->id)
            ->where('location_id', $location->id)
            ->value('quantity'))->toBe('0.000')
        ->and(WarehouseSlip::query()->where('status', 'posted')->count())->toBe(1);
});

it('cost.view olmayan depo kullanıcısının giriş fişine maliyet yazmasını reddeder', function () {
    [$company, $period] = $this->createCompanyWithPeriod('SLIPCOST');
    $warehouseUser = $this->createUserWithPeriodAccess($company, $period, 'Depo');
    $this->loginToPeriod($warehouseUser, $company, $period);

    $product = $this->createTestProduct(['code' => 'SLP-C']);
    $location = faz2Warehouse('SC-A');

    expect(fn () => app(SaveWarehouseSlipDraft::class)->handle([
        'location_id' => $location->id,
        'slip_date' => '2026-06-01',
        'direction' => 'in',
        'reason' => 'adjustment',
        'lines' => [[
            'product_id' => $product->id,
            'quantity' => '1.000',
            'unit_cost' => '25.0000',
            'note' => null,
        ]],
    ]))->toThrow(AuthorizationException::class);
});

it('sayım frozen snapshot farkını uygular ve aradaki meşru hareketi korur', function () {
    [$company, $period] = $this->createCompanyWithPeriod('COUNT');
    $admin = $this->createUserWithPeriodAccess($company, $period, 'Yönetici');
    $this->loginToPeriod($admin, $company, $period);

    $location = faz2Warehouse('CNT-A');
    $approvedProduct = $this->createTestProduct(['code' => 'CNT-1']);
    $unapprovedProduct = $this->createTestProduct(['code' => 'CNT-2']);

    faz2Movement($approvedProduct->id, $location->id, '2026-07-01', 'in', 'purchase', '10', '100', true);
    faz2Movement($unapprovedProduct->id, $location->id, '2026-07-01', 'in', 'purchase', '10', '100', true);

    $count = app(SaveStockCountDraft::class)->handle([
        'location_id' => $location->id,
        'count_date' => '2026-07-02',
        'product_ids' => [$approvedProduct->id, $unapprovedProduct->id],
    ]);

    $count = app(StartStockCount::class)->handle($count->id, (string) Str::uuid());

    expect((string) $count->lines()->where('product_id', $approvedProduct->id)->value('system_quantity'))->toBe('10.000');

    faz2Movement($approvedProduct->id, $location->id, '2026-07-02', 'out', 'sale', '3');

    $approvedLine = $count->lines()->where('product_id', $approvedProduct->id)->firstOrFail();
    $unapprovedLine = $count->lines()->where('product_id', $unapprovedProduct->id)->firstOrFail();

    app(SaveStockCountLine::class)->handle($count->id, $approvedLine->id, '8.000');
    app(SaveStockCountLine::class)->handle($count->id, $unapprovedLine->id, '8.000');

    $count = app(ReviewStockCount::class)->handle($count->id);
    app(SaveStockCountLine::class)->handle($count->id, $approvedLine->id, '8.000', null, true);

    $count = app(PostStockCount::class)->handle($count->id, (string) Str::uuid());

    expect($count->status)->toBe('posted')
        ->and((string) StockBalance::query()->where('product_id', $approvedProduct->id)->where('location_id', $location->id)->value('quantity'))->toBe('5.000')
        ->and((string) StockBalance::query()->where('product_id', $unapprovedProduct->id)->where('location_id', $location->id)->value('quantity'))->toBe('10.000')
        ->and(StockMovement::query()->where('document_type', 'stock_count')->where('document_id', $count->id)->count())->toBe(1);
});

it('karantina release ve scrap fiziksel ve kullanılabilir stoğu doğru ayırır', function () {
    [$company, $period] = $this->createCompanyWithPeriod('QUAR');
    $admin = $this->createUserWithPeriodAccess($company, $period, 'Yönetici');
    $this->loginToPeriod($admin, $company, $period);

    $product = $this->createTestProduct(['code' => 'QR-1']);
    $location = faz2Warehouse('QR-A');

    $entry = app(ReceiveToQuarantine::class)->handle(new QuarantineReceiptData(
        productId: $product->id,
        locationId: $location->id,
        movementDate: '2026-08-01',
        quantity: '5.000',
        unitCost: '100.0000',
        sourceDocumentType: 'sales_return',
        sourceDocumentId: 77,
        sourceLineId: 1,
        actorUserId: $admin->id,
        actorUserName: $admin->name,
    ), (string) Str::uuid());

    $balance = StockBalance::query()->where('product_id', $product->id)->where('location_id', $location->id)->firstOrFail();

    expect((string) $balance->quantity)->toBe('5.000')
        ->and((string) $balance->quarantine)->toBe('5.000')
        ->and($balance->available())->toBe('0.000');

    $movementCount = StockMovement::query()->count();

    $entry = app(ReleaseQuarantine::class)->handle($entry->id, '2.000', (string) Str::uuid());
    $balance->refresh();

    expect($entry->status)->toBe('partial')
        ->and((string) $balance->quantity)->toBe('5.000')
        ->and((string) $balance->quarantine)->toBe('3.000')
        ->and($balance->available())->toBe('2.000')
        ->and(StockMovement::query()->count())->toBe($movementCount);

    $entry = app(ScrapQuarantine::class)->handle(
        $entry->id,
        '1.000',
        (string) Str::uuid(),
        'Hasarlı',
        '2026-08-02',
    );
    $balance->refresh();

    expect($entry->status)->toBe('partial')
        ->and((string) $balance->quantity)->toBe('4.000')
        ->and((string) $balance->quarantine)->toBe('2.000')
        ->and($balance->available())->toBe('2.000')
        ->and(StockMovement::query()->latest('id')->firstOrFail()->reason)->toBe('scrap');
});

it('rezervasyon fiziksel stoğu değiştirmez ve karantinayı kullanmaz', function () {
    [$company, $period] = $this->createCompanyWithPeriod('RESERVE');
    $admin = $this->createUserWithPeriodAccess($company, $period, 'Yönetici');
    $this->loginToPeriod($admin, $company, $period);

    $product = $this->createTestProduct(['code' => 'RSV-1']);
    $location = faz2Warehouse('RSV-A');

    faz2Movement($product->id, $location->id, '2026-09-01', 'in', 'purchase', '10', '50', true);

    app(ReceiveToQuarantine::class)->handle(new QuarantineReceiptData(
        productId: $product->id,
        locationId: $location->id,
        movementDate: '2026-09-02',
        quantity: '5.000',
        unitCost: '50.0000',
        sourceDocumentType: 'sales_return',
        sourceDocumentId: 88,
        sourceLineId: 1,
        actorUserId: $admin->id,
        actorUserName: $admin->name,
    ), (string) Str::uuid());

    $result = app(ReserveStock::class)->handle(
        productId: $product->id,
        requestedQuantity: '12.000',
        orderedLocationIds: [$location->id],
        documentType: 'sales_order',
        documentId: 10,
        documentLineId: 11,
        idempotencyKey: (string) Str::uuid(),
        actorUserId: $admin->id,
        actorUserName: $admin->name,
    );

    $balance = StockBalance::query()->where('product_id', $product->id)->where('location_id', $location->id)->firstOrFail();

    expect($result->reservedQuantity)->toBe('10.000')
        ->and($result->openQuantity)->toBe('2.000')
        ->and((string) $balance->quantity)->toBe('15.000')
        ->and((string) $balance->reserved)->toBe('10.000')
        ->and((string) $balance->quarantine)->toBe('5.000')
        ->and($balance->available())->toBe('0.000');

    $movementCount = StockMovement::query()->count();
    $reservation = StockReservation::query()->findOrFail($result->reservationIds[0]);

    app(ReleaseReservation::class)->handle($reservation->id, (string) Str::uuid());
    $balance->refresh();

    expect((string) $balance->reserved)->toBe('0.000')
        ->and(StockMovement::query()->count())->toBe($movementCount);

    $second = app(ReserveStock::class)->handle(
        productId: $product->id,
        requestedQuantity: '6.000',
        orderedLocationIds: [$location->id],
        documentType: 'sales_order',
        documentId: 12,
        documentLineId: 13,
        idempotencyKey: (string) Str::uuid(),
        actorUserId: $admin->id,
        actorUserName: $admin->name,
    );

    $secondReservation = StockReservation::query()->findOrFail($second->reservationIds[0]);
    app(ConsumeReservation::class)->handle($secondReservation->id, (string) Str::uuid());

    expect((string) $balance->refresh()->reserved)->toBe('0.000')
        ->and($secondReservation->refresh()->status)->toBe('consumed')
        ->and(StockMovement::query()->count())->toBe($movementCount);
});

it('1000 satırlık açılışı atomik uygular ve ikinci açılışı reddeder', function () {
    [$company, $period] = $this->createCompanyWithPeriod('OPENING');
    $admin = $this->createUserWithPeriodAccess($company, $period, 'Yönetici');
    $this->loginToPeriod($admin, $company, $period);

    $product = $this->createTestProduct(['code' => 'OPEN-1']);
    $location = faz2Warehouse('OPEN-A');

    $rows = array_fill(0, 1000, [
        'product_code' => $product->code,
        'location_code' => $location->code,
        'quantity' => '1.000',
        'unit_cost' => '10.0000',
    ]);

    app(ImportOpeningStock::class)->handle(
        $rows,
        '2026-01-15',
        (string) Str::uuid(),
        $admin->id,
        $admin->name,
    );

    expect(StockMovement::query()->where('reason', 'opening')->count())->toBe(1000)
        ->and((string) StockBalance::query()->where('product_id', $product->id)->where('location_id', $location->id)->value('quantity'))->toBe('1000.000')
        ->and((string) ProductCost::query()->where('product_id', $product->id)->value('moving_average'))->toBe('10.0000');

    expect(fn () => app(ImportOpeningStock::class)->handle(
        [$rows[0]],
        '2026-01-15',
        (string) Str::uuid(),
        $admin->id,
        $admin->name,
    ))->toThrow('DomainException');
});

it('rezervasyonu lokasyon önceliğine göre birden fazla depoya böler', function () {
    [$company, $period] = $this->createCompanyWithPeriod('RESPLIT');
    $admin = $this->createUserWithPeriodAccess($company, $period, 'Yönetici');
    $this->loginToPeriod($admin, $company, $period);

    $product = $this->createTestProduct(['code' => 'RSV-SPLIT']);
    $first = faz2Warehouse('RSV-S1');
    $second = faz2Warehouse('RSV-S2');

    faz2Movement($product->id, $first->id, '2026-09-01', 'in', 'purchase', '3', '10', true);
    faz2Movement($product->id, $second->id, '2026-09-01', 'in', 'purchase', '5', '10', true);

    $result = app(ReserveStock::class)->handle(
        productId: $product->id,
        requestedQuantity: '6.000',
        orderedLocationIds: [$first->id, $second->id],
        documentType: 'sales_order',
        documentId: 20,
        documentLineId: 21,
        idempotencyKey: (string) Str::uuid(),
        actorUserId: $admin->id,
        actorUserName: $admin->name,
    );

    $reservations = StockReservation::query()
        ->whereIn('id', $result->reservationIds)
        ->orderBy('id')
        ->get();

    expect($result->reservedQuantity)->toBe('6.000')
        ->and($result->openQuantity)->toBe('0.000')
        ->and($reservations)->toHaveCount(2)
        ->and((string) $reservations[0]->quantity)->toBe('3.000')
        ->and((string) $reservations[1]->quantity)->toBe('3.000')
        ->and($reservations[0]->location_id)->toBe($first->id)
        ->and($reservations[1]->location_id)->toBe($second->id);
});

it('hatalı açılış satırında tüm açılış transactionını geri alır', function () {
    [$company, $period] = $this->createCompanyWithPeriod('OPENROLL');
    $admin = $this->createUserWithPeriodAccess($company, $period, 'Yönetici');
    $this->loginToPeriod($admin, $company, $period);

    $product = $this->createTestProduct(['code' => 'OPEN-ROLL']);
    $location = faz2Warehouse('OPEN-R');

    expect(fn () => app(ImportOpeningStock::class)->handle(
        [
            [
                'product_code' => $product->code,
                'location_code' => $location->code,
                'quantity' => '5.000',
                'unit_cost' => '10.0000',
            ],
            [
                'product_code' => 'YOK-URUN',
                'location_code' => $location->code,
                'quantity' => '2.000',
                'unit_cost' => '10.0000',
            ],
        ],
        '2026-01-10',
        (string) Str::uuid(),
        $admin->id,
        $admin->name,
    ))->toThrow(ModelNotFoundException::class);

    expect(StockMovement::query()->where('reason', 'opening')->count())->toBe(0)
        ->and(StockBalance::query()->where('product_id', $product->id)->count())->toBe(0)
        ->and(ProductCost::query()->where('product_id', $product->id)->count())->toBe(0);
});

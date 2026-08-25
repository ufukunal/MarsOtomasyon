<?php

use App\Foundation\Idempotency\IdempotencyConflict;
use App\Foundation\Identity\SourceEffectIdentity;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Tax;
use App\Modules\Inventory\Enums\StockMovementType;
use App\Modules\Inventory\Ledger\PostStockMovementData;
use App\Modules\Inventory\Ledger\StockMovementPoster;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Models\WarehouseLocation;
use App\Modules\Inventory\Models\WarehouseTransfer;
use App\Modules\Inventory\Models\WarehouseTransferLine;
use App\Modules\Inventory\Models\WarehouseTransferReceipt;
use App\Modules\Inventory\Transfers\WarehouseTransferIssueLineData;
use App\Modules\Inventory\Transfers\WarehouseTransferIssueResult;
use App\Modules\Inventory\Transfers\WarehouseTransferReceiptResult;
use App\Modules\Inventory\Transfers\WarehouseTransferService;
use App\Modules\Products\Enums\ProductStatus;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\Unit;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

uses(DatabaseMigrations::class);

it('issues a warehouse transfer exactly once and freezes source carrying cost in transit custody', function (): void {
    [$company, $product, $sourceWarehouse, $source, $destinationWarehouse, $destination] = m46StockContext('M46-ISSUE');
    m46PostOpening($company, $product, $sourceWarehouse, $source, '10', '100');
    $identity = m46Identity($company, 'transfer-issue-1', 'inventory.transfer_issue');

    $first = DB::transaction(fn (): WarehouseTransferIssueResult => app(WarehouseTransferService::class)->issue(
        $identity,
        (int) $sourceWarehouse->getKey(),
        (int) $source->getKey(),
        (int) $destinationWarehouse->getKey(),
        (int) $destination->getKey(),
        [new WarehouseTransferIssueLineData((int) $product->getKey(), '4')],
    ));
    $replay = DB::transaction(fn (): WarehouseTransferIssueResult => app(WarehouseTransferService::class)->issue(
        $identity,
        (int) $sourceWarehouse->getKey(),
        (int) $source->getKey(),
        (int) $destinationWarehouse->getKey(),
        (int) $destination->getKey(),
        [new WarehouseTransferIssueLineData((int) $product->getKey(), '4.000000')],
    ));

    $line = WarehouseTransferLine::query()->firstOrFail();
    $issueMovement = StockMovement::query()->where('movement_type', StockMovementType::TransferOut->value)->firstOrFail();
    $sourceBalance = m46Balance($product, $source);

    expect($first->replayed)->toBeFalse()
        ->and($replay->replayed)->toBeTrue()
        ->and($replay->transfer->getKey())->toBe($first->transfer->getKey())
        ->and($first->transfer->status)->toBe('in_transit')
        ->and($line->issued_quantity)->toBe('4.000000')
        ->and($line->received_quantity)->toBe('0.000000')
        ->and($line->in_transit_quantity)->toBe('4.000000')
        ->and($line->unit_cost)->toBe('100.000000')
        ->and($line->issued_value)->toBe('400.000000')
        ->and($line->in_transit_value)->toBe('400.000000')
        ->and($line->issue_movement_id)->toBe($issueMovement->getKey())
        ->and($sourceBalance->quantity)->toBe('6.000000')
        ->and($sourceBalance->inventory_value)->toBe('600.000000')
        ->and(WarehouseTransfer::query()->count())->toBe(1)
        ->and(WarehouseTransferLine::query()->count())->toBe(1)
        ->and(StockMovement::query()->count())->toBe(2);

    expect(fn () => DB::transaction(fn (): WarehouseTransferIssueResult => app(WarehouseTransferService::class)->issue(
        $identity,
        (int) $sourceWarehouse->getKey(),
        (int) $source->getKey(),
        (int) $destinationWarehouse->getKey(),
        (int) $destination->getKey(),
        [new WarehouseTransferIssueLineData((int) $product->getKey(), '5')],
    )))->toThrow(IdempotencyConflict::class);
});

it('receives partial and final quantities while preserving the exact in-transit carrying value', function (): void {
    [$company, $product, $sourceWarehouse, $source, $destinationWarehouse, $destination] = m46StockContext('M46-RECEIPT');
    m46PostOpening($company, $product, $sourceWarehouse, $source, '6', '33.333333');
    $issue = m46Issue($company, $product, $sourceWarehouse, $source, $destinationWarehouse, $destination, '3');
    $line = WarehouseTransferLine::query()->where('transfer_id', $issue->transfer->getKey())->firstOrFail();

    $partialIdentity = m46Identity($company, 'receipt-partial', 'inventory.transfer_in');
    $partial = DB::transaction(fn (): WarehouseTransferReceiptResult => app(WarehouseTransferService::class)->receive(
        $partialIdentity,
        (int) $issue->transfer->getKey(),
        (int) $line->getKey(),
        '1',
    ));
    $partialReplay = DB::transaction(fn (): WarehouseTransferReceiptResult => app(WarehouseTransferService::class)->receive(
        $partialIdentity,
        (int) $issue->transfer->getKey(),
        (int) $line->getKey(),
        '1.000000',
    ));

    $line->refresh();
    $partial->transfer->refresh();
    $destinationBalance = m46Balance($product, $destination);

    expect($partial->replayed)->toBeFalse()
        ->and($partialReplay->replayed)->toBeTrue()
        ->and($partial->receipt->carrying_value)->toBe('33.333333')
        ->and($partial->transfer->status)->toBe('partially_received')
        ->and($line->received_quantity)->toBe('1.000000')
        ->and($line->received_value)->toBe('33.333333')
        ->and($line->in_transit_quantity)->toBe('2.000000')
        ->and($line->in_transit_value)->toBe('66.666666')
        ->and($destinationBalance->quantity)->toBe('1.000000')
        ->and($destinationBalance->inventory_value)->toBe('33.333333');

    $final = DB::transaction(fn (): WarehouseTransferReceiptResult => app(WarehouseTransferService::class)->receive(
        m46Identity($company, 'receipt-final', 'inventory.transfer_in'),
        (int) $issue->transfer->getKey(),
        (int) $line->getKey(),
        '2',
    ));

    $line->refresh();
    $final->transfer->refresh();
    $destinationBalance->refresh();
    $receivedValue = WarehouseTransferReceipt::query()
        ->where('transfer_id', $issue->transfer->getKey())
        ->sum('carrying_value');

    expect($final->receipt->carrying_value)->toBe('66.666666')
        ->and($final->transfer->status)->toBe('received')
        ->and($final->transfer->completed_at)->not->toBeNull()
        ->and($line->received_quantity)->toBe('3.000000')
        ->and($line->received_value)->toBe('99.999999')
        ->and($line->in_transit_quantity)->toBe('0.000000')
        ->and($line->in_transit_value)->toBe('0.000000')
        ->and((string) $receivedValue)->toBe('99.999999')
        ->and($destinationBalance->quantity)->toBe('3.000000')
        ->and($destinationBalance->inventory_value)->toBe('99.999999')
        ->and(WarehouseTransferReceipt::query()->count())->toBe(2);
});

it('blocks over receipt and protects custody projections at the PostgreSQL boundary', function (): void {
    [$company, $product, $sourceWarehouse, $source, $destinationWarehouse, $destination] = m46StockContext('M46-GUARD');
    m46PostOpening($company, $product, $sourceWarehouse, $source, '5', '40');
    $issue = m46Issue($company, $product, $sourceWarehouse, $source, $destinationWarehouse, $destination, '3');
    $line = WarehouseTransferLine::query()->firstOrFail();

    expect(fn () => DB::transaction(fn (): WarehouseTransferReceiptResult => app(WarehouseTransferService::class)->receive(
        m46Identity($company, 'over-receipt', 'inventory.transfer_in'),
        (int) $issue->transfer->getKey(),
        (int) $line->getKey(),
        '4',
    )))->toThrow(ValidationException::class);

    expect(fn () => DB::table('warehouse_transfer_lines')->where('id', $line->getKey())->update([
        'received_quantity' => '1.000000',
        'received_value' => '40.000000',
    ]))->toThrow(QueryException::class);
    expect(fn () => DB::table('warehouse_transfers')->where('id', $issue->transfer->getKey())->update([
        'status' => 'received',
        'completed_at' => now(),
    ]))->toThrow(QueryException::class);
    expect(fn () => DB::table('warehouse_transfer_lines')->where('id', $line->getKey())->delete())
        ->toThrow(QueryException::class);

    $line->refresh();
    expect($line->received_quantity)->toBe('0.000000')
        ->and($line->in_transit_quantity)->toBe('3.000000')
        ->and($issue->transfer->refresh()->status)->toBe('in_transit');
});

it('requires the owning business transaction and rejects receipts after completion', function (): void {
    [$company, $product, $sourceWarehouse, $source, $destinationWarehouse, $destination] = m46StockContext('M46-TX');
    m46PostOpening($company, $product, $sourceWarehouse, $source, '4', '25');
    $service = app(WarehouseTransferService::class);

    expect(fn () => $service->issue(
        m46Identity($company, 'outside-issue', 'inventory.transfer_issue'),
        (int) $sourceWarehouse->getKey(),
        (int) $source->getKey(),
        (int) $destinationWarehouse->getKey(),
        (int) $destination->getKey(),
        [new WarehouseTransferIssueLineData((int) $product->getKey(), '1')],
    ))->toThrow(LogicException::class);

    $issue = m46Issue($company, $product, $sourceWarehouse, $source, $destinationWarehouse, $destination, '2');
    $line = WarehouseTransferLine::query()->firstOrFail();

    expect(fn () => $service->receive(
        m46Identity($company, 'outside-receipt', 'inventory.transfer_in'),
        (int) $issue->transfer->getKey(),
        (int) $line->getKey(),
        '1',
    ))->toThrow(LogicException::class);

    DB::transaction(fn (): WarehouseTransferReceiptResult => $service->receive(
        m46Identity($company, 'complete-receipt', 'inventory.transfer_in'),
        (int) $issue->transfer->getKey(),
        (int) $line->getKey(),
        '2',
    ));

    expect(fn () => DB::transaction(fn (): WarehouseTransferReceiptResult => $service->receive(
        m46Identity($company, 'after-complete', 'inventory.transfer_in'),
        (int) $issue->transfer->getKey(),
        (int) $line->getKey(),
        '1',
    )))->toThrow(ValidationException::class);
});

function m46Identity(Company $company, string $sourceId, string $effectType): SourceEffectIdentity
{
    return new SourceEffectIdentity(
        companyId: (int) $company->getKey(),
        sourceType: 'inventory.test-transfer',
        sourceId: $sourceId,
        effectType: $effectType,
    );
}

function m46PostOpening(
    Company $company,
    Product $product,
    Warehouse $warehouse,
    WarehouseLocation $location,
    string $quantity,
    string $unitCost,
): void {
    DB::transaction(fn () => app(StockMovementPoster::class)->post(new PostStockMovementData(
        sourceEffect: m46Identity($company, 'opening-'.$company->code, 'inventory.opening'),
        productId: (int) $product->getKey(),
        warehouseId: (int) $warehouse->getKey(),
        locationId: (int) $location->getKey(),
        movementType: StockMovementType::OpeningIn,
        quantity: $quantity,
        unitCost: $unitCost,
    )));
}

function m46Issue(
    Company $company,
    Product $product,
    Warehouse $sourceWarehouse,
    WarehouseLocation $source,
    Warehouse $destinationWarehouse,
    WarehouseLocation $destination,
    string $quantity,
): WarehouseTransferIssueResult {
    return DB::transaction(fn (): WarehouseTransferIssueResult => app(WarehouseTransferService::class)->issue(
        m46Identity($company, 'issue-'.$company->code, 'inventory.transfer_issue'),
        (int) $sourceWarehouse->getKey(),
        (int) $source->getKey(),
        (int) $destinationWarehouse->getKey(),
        (int) $destination->getKey(),
        [new WarehouseTransferIssueLineData((int) $product->getKey(), $quantity)],
    ));
}

function m46Balance(Product $product, WarehouseLocation $location): StockBalance
{
    return StockBalance::query()
        ->where('product_id', $product->getKey())
        ->where('location_id', $location->getKey())
        ->firstOrFail();
}

/** @return array{Company, Product, Warehouse, WarehouseLocation, Warehouse, WarehouseLocation} */
function m46StockContext(string $code): array
{
    $company = Company::query()->create(['code' => $code, 'name' => 'Company '.$code]);
    $category = Category::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'LIGHT',
        'name' => 'Aydınlatma',
        'is_active' => true,
    ]);
    $unit = Unit::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'ADET',
        'name' => 'Adet',
        'is_active' => true,
    ]);
    $tax = Tax::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'KDV20',
        'name' => 'KDV %20',
        'rate' => '20.000000',
        'is_active' => true,
    ]);
    $product = Product::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'SKU-'.$code,
        'status' => ProductStatus::Active,
        'name' => 'Ürün '.$code,
        'category_id' => $category->getKey(),
        'unit_id' => $unit->getKey(),
        'tax_id' => $tax->getKey(),
        'sale_price_net' => '100.000000',
        'purchase_price_net' => '60.000000',
    ]);
    $sourceWarehouse = Warehouse::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'SRC',
        'name' => 'Kaynak Depo',
        'is_active' => true,
    ]);
    $source = WarehouseLocation::query()->create([
        'company_id' => $company->getKey(),
        'warehouse_id' => $sourceWarehouse->getKey(),
        'code' => 'A-01',
        'name' => 'Kaynak Raf',
        'is_active' => true,
    ]);
    $destinationWarehouse = Warehouse::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'DST',
        'name' => 'Hedef Depo',
        'is_active' => true,
    ]);
    $destination = WarehouseLocation::query()->create([
        'company_id' => $company->getKey(),
        'warehouse_id' => $destinationWarehouse->getKey(),
        'code' => 'B-01',
        'name' => 'Hedef Raf',
        'is_active' => true,
    ]);

    return [$company, $product, $sourceWarehouse, $source, $destinationWarehouse, $destination];
}

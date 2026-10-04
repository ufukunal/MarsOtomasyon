<?php

use App\Actions\Stock\RecordStockMovement;
use App\DataObjects\StockMovementData;
use App\Models\Period\Location;
use App\Models\Period\StockBalance;
use App\Models\Period\StockMovement;
use App\Support\Period\PeriodContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function faz2ContractWarehouse(string $code): Location
{
    return Location::query()->create([
        'code' => $code,
        'name' => $code.' Depo',
        'kind' => 'warehouse',
        'is_default' => false,
        'is_active' => true,
    ]);
}

it('Faz 2 period stok tablolarında company_id ve Master FK barındırmaz', function () {
    [$company, $period] = $this->createCompanyWithPeriod('SCHEMA');
    PeriodContext::useSystem($company->id, $period->id);

    $tables = [
        'stock_movements',
        'stock_balances',
        'product_costs',
        'transfers',
        'transfer_lines',
        'warehouse_slips',
        'warehouse_slip_lines',
        'stock_counts',
        'stock_count_lines',
        'quarantine_entries',
        'stock_reservations',
    ];

    foreach ($tables as $table) {
        expect(Schema::connection('period')->hasTable($table))
            ->toBeTrue("{$table} period tablosu bulunamadı.")
            ->and(Schema::connection('period')->hasColumn($table, 'company_id'))
            ->toBeFalse("{$table}.company_id period izolasyonunu ihlal ediyor.");
    }

    $placeholders = implode(', ', array_fill(0, count($tables), '?'));
    $foreignKeys = DB::connection('period')->select(
        <<<SQL
            SELECT
                tc.table_name,
                kcu.column_name,
                ccu.table_name AS foreign_table_name
            FROM information_schema.table_constraints tc
            JOIN information_schema.key_column_usage kcu
              ON kcu.constraint_name = tc.constraint_name
             AND kcu.constraint_schema = tc.constraint_schema
            JOIN information_schema.constraint_column_usage ccu
              ON ccu.constraint_name = tc.constraint_name
             AND ccu.constraint_schema = tc.constraint_schema
            WHERE tc.constraint_type = 'FOREIGN KEY'
              AND tc.table_schema = current_schema()
              AND tc.table_name IN ({$placeholders})
            ORDER BY tc.table_name, kcu.column_name
        SQL,
        $tables,
    );

    $masterTables = ['users', 'companies', 'periods', 'roles', 'permissions'];
    $masterForeignKeys = array_values(array_filter(
        $foreignKeys,
        fn (object $row): bool => in_array((string) $row->foreign_table_name, $masterTables, true),
    ));

    expect($masterForeignKeys)->toBe([]);
});

it('A periodindeki stok hareketi B periodinde görünmez ve A ürün kimliği B döneminde kullanılamaz', function () {
    [$companyA, $periodA] = $this->createCompanyWithPeriod('ISOA');
    [$companyB, $periodB] = $this->createCompanyWithPeriod('ISOB');

    PeriodContext::useSystem($companyA->id, $periodA->id);
    $productA = $this->createTestProduct(['code' => 'ISO-A-P']);
    $locationA = faz2ContractWarehouse('ISO-A-L');

    app(RecordStockMovement::class)->handle(new StockMovementData(
        productId: $productA->id,
        locationId: $locationA->id,
        movementDate: '2026-12-01',
        direction: 'in',
        reason: 'purchase',
        quantity: '5.000',
        unitCost: '12.5000',
        updatesAverage: true,
    ));

    expect(StockMovement::query()->count())->toBe(1);

    PeriodContext::useSystem($companyB->id, $periodB->id);

    expect(StockMovement::query()->count())->toBe(0)
        ->and(StockBalance::query()->count())->toBe(0);

    expect(fn () => app(RecordStockMovement::class)->handle(new StockMovementData(
        productId: $productA->id,
        locationId: $locationA->id,
        movementDate: '2026-12-01',
        direction: 'in',
        reason: 'purchase',
        quantity: '1.000',
        unitCost: '12.5000',
        updatesAverage: true,
    )))->toThrow(ModelNotFoundException::class);

    PeriodContext::useSystem($companyA->id, $periodA->id);

    expect(StockMovement::query()->count())->toBe(1)
        ->and((string) StockBalance::query()
            ->where('product_id', $productA->id)
            ->where('location_id', $locationA->id)
            ->value('quantity'))->toBe('5.000');
});

it('kullanılabilir stok rezerve konsinye ve karantinayı düşer fiziksel stoğu değiştirmez', function () {
    [$company, $period] = $this->createCompanyWithPeriod('AVAILABLE');
    PeriodContext::useSystem($company->id, $period->id);

    $product = $this->createTestProduct(['code' => 'AVL-1']);
    $location = faz2ContractWarehouse('AVL-A');

    $balance = StockBalance::query()->create([
        'product_id' => $product->id,
        'location_id' => $location->id,
        'quantity' => '10.000',
        'reserved' => '2.000',
        'consignment_reserved' => '3.000',
        'quarantine' => '1.000',
    ]);

    expect($balance->available())->toBe('4.000')
        ->and((string) $balance->quantity)->toBe('10.000')
        ->and((string) $balance->reserved)->toBe('2.000')
        ->and((string) $balance->consignment_reserved)->toBe('3.000')
        ->and((string) $balance->quarantine)->toBe('1.000');
});

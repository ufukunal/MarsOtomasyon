<?php

use App\Actions\Stock\RecordStockMovement;
use App\DataObjects\StockMovementData;
use App\Models\Period\Location;
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

it('Faz 2 period şemasını company_id ve Master FK olmadan kurar', function () {
    [$company, $period] = $this->createCompanyWithPeriod('SCHEMA');

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
        expect(Schema::connection('period')->hasTable($table))->toBeTrue();
        expect(Schema::connection('period')->hasColumn($table, 'company_id'))
            ->toBeFalse("{$table} period tablosunda company_id olamaz.");
    }

    $foreignKeys = DB::connection('period')->select(<<<'SQL'
        SELECT
            source.relname AS source_table,
            target.relname AS target_table
        FROM pg_constraint constraint_row
        JOIN pg_class source ON source.oid = constraint_row.conrelid
        JOIN pg_class target ON target.oid = constraint_row.confrelid
        WHERE constraint_row.contype = 'f'
          AND source.relname = ANY (ARRAY[
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
            'stock_reservations'
          ])
        ORDER BY source.relname, target.relname
    SQL);

    $masterOnly = ['users', 'companies', 'periods', 'roles', 'permissions'];

    foreach ($foreignKeys as $foreignKey) {
        expect($masterOnly)->not->toContain($foreignKey->target_table);
    }

    expect(Schema::connection('period')->hasTable('purchase_requests'))->toBeFalse()
        ->and(Schema::connection('period')->hasTable('supplier_quotes'))->toBeFalse();
});

it('Faz 2 stok hareketlerini fiziksel period veritabanları arasında izole eder', function () {
    [$companyA, $periodA] = $this->createCompanyWithPeriod('STKISOA');
    $productA = $this->createTestProduct(['code' => 'ISO-A']);
    $locationA = faz2ContractWarehouse('ISO-A-WH');

    app(RecordStockMovement::class)->handle(new StockMovementData(
        productId: $productA->id,
        locationId: $locationA->id,
        movementDate: '2026-12-01',
        direction: 'in',
        reason: 'purchase',
        quantity: '3.000',
        unitCost: '75.0000',
        updatesAverage: true,
    ));

    expect(StockMovement::query()->count())->toBe(1);

    [$companyB, $periodB] = $this->createCompanyWithPeriod('STKISOB');

    expect(config('database.connections.period.database'))->toBe($periodB->database_name)
        ->and(StockMovement::query()->count())->toBe(0);

    expect(fn () => app(RecordStockMovement::class)->handle(new StockMovementData(
        productId: $productA->id,
        locationId: $locationA->id,
        movementDate: '2026-12-01',
        direction: 'in',
        reason: 'purchase',
        quantity: '1.000',
        unitCost: '75.0000',
        updatesAverage: true,
    )))->toThrow(ModelNotFoundException::class);

    PeriodContext::useSystem($companyA->id, $periodA->id);

    expect(StockMovement::query()->count())->toBe(1)
        ->and((string) StockMovement::query()->firstOrFail()->quantity)->toBe('3.000');
});

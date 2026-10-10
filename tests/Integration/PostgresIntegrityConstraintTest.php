<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Support\IsolatedPostgres;

it('enforces positive physical stock allocations in PostgreSQL, not just PHP', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        $ids = IsolatedPostgres::productAndLocation();
        $db = DB::connection('period');
        $key = $db->table('stock_balances')->insertGetId([
            'product_id' => $ids['product'], 'location_id' => $ids['location'],
            'quantity' => '10.000', 'reserved' => '0.000',
            'consignment_reserved' => '0.000', 'quarantine' => '0.000',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $db->beginTransaction(); // Savepoint: PostgreSQL errors need rollback before further SQL.
        $rejected = false;
        try {
            $db->table('stock_balances')->where('id', $key)->update(['reserved' => '-1.000']);
        } catch (QueryException) {
            $rejected = true;
        } finally {
            $db->rollBack();
        }
        expect($rejected)->toBeTrue();
        expect((string) $db->table('stock_balances')->where('id', $key)->value('reserved'))->toBe('0.000');
    });
});

it('prevents duplicate inventory balance rows for the same product and location', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        $ids = IsolatedPostgres::productAndLocation();
        $db = DB::connection('period');
        $row = [
            'product_id' => $ids['product'], 'location_id' => $ids['location'],
            'quantity' => '0.000', 'created_at' => now(), 'updated_at' => now(),
        ];
        $db->table('stock_balances')->insert($row);
        $db->beginTransaction();
        $rejected = false;
        try {
            $db->table('stock_balances')->insert($row);
        } catch (QueryException) {
            $rejected = true;
        } finally {
            $db->rollBack();
        }
        expect($rejected)->toBeTrue();
    });
});

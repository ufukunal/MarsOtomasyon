<?php

use App\Support\Integrity\Checks\ReservationBalanceCheck;
use App\Support\Integrity\Checks\StockBalanceCheck;
use Illuminate\Support\Facades\DB;
use Tests\Support\IsolatedPostgres;

it('detects a forged physical stock balance unsupported by any inventory movement', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        $ids = IsolatedPostgres::productAndLocation();
        DB::connection('period')->table('stock_balances')->insert([
            'product_id' => $ids['product'],
            'location_id' => $ids['location'],
            'quantity' => '50.000',
            'reserved' => '0.000',
            'consignment_reserved' => '0.000',
            'quarantine' => '0.000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $result = app(StockBalanceCheck::class)->run();

        expect($result->mismatchCount())->toBe(1)
            ->and((int) $result->mismatches[0]['product_id'])->toBe($ids['product'])
            ->and((string) $result->mismatches[0]['stored'])->toBe('50.000')
            ->and((string) $result->mismatches[0]['calculated'])->toBe('0');
    });
});

it('detects reservations present in the ledger but missing from the physical reserved summary', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        $ids = IsolatedPostgres::productAndLocation();
        $db = DB::connection('period');
        $db->table('stock_balances')->insert([
            'product_id' => $ids['product'],
            'location_id' => $ids['location'],
            'quantity' => '10.000',
            'reserved' => '0.000',
            'consignment_reserved' => '0.000',
            'quarantine' => '0.000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $db->table('stock_reservations')->insert([
            'product_id' => $ids['product'],
            'location_id' => $ids['location'],
            'quantity' => '2.000',
            'document_type' => 'sales_order',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $result = app(ReservationBalanceCheck::class)->run();

        expect($result->mismatchCount())->toBe(1)
            ->and((int) $result->mismatches[0]['product_id'])->toBe($ids['product'])
            ->and((string) $result->mismatches[0]['stored'])->toBe('0.000')
            ->and((string) $result->mismatches[0]['calculated'])->toBe('2.000');
    });
});

<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Support\IsolatedPostgres;

function marsRejectPeriodSql(callable $query): bool
{
    $db = DB::connection('period');
    $db->beginTransaction();
    $rejected = false;
    try {
        $query($db);
    } catch (QueryException) {
        $rejected = true;
    } finally {
        $db->rollBack();
    }
    return $rejected;
}

it('enforces financial document total invariants in PostgreSQL', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        $db = DB::connection('period');
        $id = $db->table('documents')->insertGetId([
            'document_type' => 'collection', 'document_date' => '2026-10-10',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        expect(marsRejectPeriodSql(fn ($conn) => $conn->table('documents')
            ->where('id', $id)->update(['grand_total' => '99.0000'])))->toBeTrue();
        expect(marsRejectPeriodSql(fn ($conn) => $conn->table('documents')
            ->where('id', $id)->update(['discount_rate' => '101'])))->toBeTrue();
        expect((string) $db->table('documents')->where('id', $id)->value('grand_total'))->toBe('0.0000');
    });
});

it('enforces stock line quantities, cancel limits and prohibits self-source chains', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        $fixture = IsolatedPostgres::productAndLocation();
        $db = DB::connection('period');
        $document = $db->table('documents')->insertGetId([
            'document_type' => 'sales_order', 'document_date' => '2026-10-10',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $line = $db->table('document_lines')->insertGetId([
            'document_id' => $document, 'line_no' => 1, 'line_kind' => 'stock',
            'product_id' => $fixture['product'], 'unit_id' => $fixture['unit'],
            'quantity' => '5.000', 'conversion_factor' => '1.000000',
            'base_quantity' => '5.000', 'location_id' => $fixture['location'],
            'unit_price' => '10.0000', 'line_total' => '50.0000',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        expect(marsRejectPeriodSql(fn ($conn) => $conn->table('document_lines')
            ->where('id', $line)->update(['quantity' => '0'])))->toBeTrue();
        expect(marsRejectPeriodSql(fn ($conn) => $conn->table('document_lines')
            ->where('id', $line)->update(['cancelled_quantity' => '6'])))->toBeTrue();
        expect(marsRejectPeriodSql(fn ($conn) => $conn->table('document_lines')
            ->where('id', $line)->update(['source_line_id' => $line])))->toBeTrue();
        expect((string) $db->table('document_lines')->where('id', $line)->value('quantity'))->toBe('5.000');
    });
});

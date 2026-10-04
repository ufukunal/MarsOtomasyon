<?php

use App\Actions\Documents\CalculateDocumentTotals;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('Faz 3 satis finans semasini period DB sinirlari icinde kurar', function () {
    [$company, $period] = $this->createCompanyWithPeriod('FAZ3SCHEMA');

    $tables = [
        'documents',
        'document_lines',
        'document_relations',
        'contact_transactions',
        'cash_accounts',
        'cash_movements',
        'bank_accounts',
        'bank_movements',
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
            'documents',
            'document_lines',
            'document_relations',
            'contact_transactions',
            'cash_accounts',
            'cash_movements',
            'bank_accounts',
            'bank_movements'
          ])
        ORDER BY source.relname, target.relname
    SQL);

    $masterOnly = ['users', 'companies', 'periods', 'roles', 'permissions'];

    foreach ($foreignKeys as $foreignKey) {
        expect($masterOnly)->not->toContain($foreignKey->target_table);
    }
});

it('Faz 3 hesap motoru satir ve belge iskontosunu KDVden once uygular', function () {
    $totals = app(CalculateDocumentTotals::class)->handle([
        [
            'quantity' => '2.000',
            'unit_price' => '100.0000',
            'line_discount_rate' => '10.0000',
            'line_discount_amount' => '0.0000',
            'vat_rate' => '20.0000',
        ],
        [
            'quantity' => '1.000',
            'unit_price' => '50.0000',
            'line_discount_rate' => '0.0000',
            'line_discount_amount' => '0.0000',
            'vat_rate' => '10.0000',
        ],
    ], '10.0000', '0.0000');

    expect($totals->lines[0]->lineTotal)->toBe('180.0000')
        ->and($totals->lines[1]->lineTotal)->toBe('50.0000')
        ->and($totals->subtotal)->toBe('230.0000')
        ->and($totals->discountAmount)->toBe('23.0000')
        ->and($totals->taxBase)->toBe('207.0000')
        ->and($totals->vatAmount)->toBe('36.9000')
        ->and($totals->roundingDifference)->toBe('0.0000')
        ->and($totals->grandTotal)->toBe('243.9000');
});

<?php

use App\Actions\Documents\CalculateDocumentTotals;
use App\Actions\Sales\IssueProforma;
use App\Enums\DocumentType;
use App\Models\Period\Document;
use App\Models\Period\DocumentLine;
use App\Models\Period\StockMovement;
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

it('Faz 3 hesap motoru satir ara hesaplarini yuvarlamaz', function () {
    $totals = app(CalculateDocumentTotals::class)->handle([
        [
            'quantity' => '0.333',
            'unit_price' => '0.3333',
            'line_discount_rate' => '0',
            'line_discount_amount' => '0',
            'vat_rate' => '0',
        ],
    ]);

    expect($totals->lines[0]->gross)->toBe('0.1109')
        ->and($totals->lines[0]->lineTotal)->toBe('0.1109')
        ->and($totals->subtotal)->toBe('0.1109')
        ->and($totals->grandTotal)->toBe('0.1100')
        ->and($totals->roundingDifference)->toBe('-0.0009');
});

it('proforma numara almadan kesinlesir ve stogu etkilemez', function () {
    [$company, $period] = $this->createCompanyWithPeriod('FAZ3PROFORMA');
    $user = $this->createUserWithPeriodAccess($company, $period, 'Yönetici');
    $this->loginToPeriod($user, $company, $period);

    $quote = Document::query()->create([
        'document_type' => DocumentType::Quote->value,
        'number' => 'TKL-TEST-1',
        'revision_no' => 1,
        'document_date' => '2026-10-01',
        'currency' => 'TRY',
        'exchange_rate' => '1.000000',
        'status' => 'approved',
        'discount_rate' => '0.0000',
        'discount_amount' => '0.0000',
        'subtotal' => '100.0000',
        'tax_base' => '100.0000',
        'vat_amount' => '20.0000',
        'rounding_difference' => '0.0000',
        'grand_total' => '120.0000',
        'created_by' => $user->id,
        'created_by_name' => $user->name,
    ]);

    DocumentLine::query()->create([
        'document_id' => $quote->id,
        'line_no' => 1,
        'line_kind' => 'service',
        'description' => 'Test hizmeti',
        'quantity' => '1.000',
        'unit_price' => '100.0000',
        'line_discount_rate' => '0.0000',
        'line_discount_amount' => '0.0000',
        'vat_rate' => '20.0000',
        'line_total' => '100.0000',
        'reserve_stock' => false,
        'cancelled_quantity' => '0.000',
    ]);

    $proforma = app(IssueProforma::class)->handle(
        $quote,
        '2026-10-01',
        'faz3-proforma-no-number',
    );

    expect($proforma->document_type)->toBe(DocumentType::Proforma)
        ->and($proforma->status)->toBe('posted')
        ->and($proforma->number)->toBeNull()
        ->and(StockMovement::query()->where('document_id', $proforma->id)->count())->toBe(0);
});

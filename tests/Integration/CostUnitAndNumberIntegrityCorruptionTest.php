<?php

use App\Actions\Numbering\GenerateDocumentNumber;
use App\Support\Integrity\Checks\CostIntegrityCheck;
use App\Support\Integrity\Checks\NumberSeriesCheck;
use App\Support\Integrity\Checks\UnitIntegrityCheck;
use Illuminate\Support\Facades\DB;
use Tests\Support\IsolatedPostgres;

it('finds a moving-average balance with no corresponding stock or cost event', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        $ids = IsolatedPostgres::productAndLocation();
        DB::connection('period')->table('product_costs')->insert([
            'product_id' => $ids['product'],
            'moving_average' => '17.5000',
            'last_purchase_price' => '0.0000',
            'import_cost' => '0.0000',
            'production_cost' => '0.0000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $result = app(CostIntegrityCheck::class)->run();

        expect(collect($result->mismatches)->contains(
            fn (array $item): bool => (int) ($item['product_id'] ?? 0) === $ids['product']
                && bccomp((string) $item['stored'], '17.5000', 4) === 0
                && bccomp((string) $item['calculated'], '0', 4) === 0,
        ))->toBeTrue();
    });
});

it('detects a document line base quantity inconsistent with its conversion factor', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        $db = DB::connection('period');
        $document = $db->table('documents')->insertGetId([
            'document_type' => 'sales_order',
            'document_date' => '2026-10-10',
            'status' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $line = $db->table('document_lines')->insertGetId([
            'document_id' => $document,
            'line_no' => 1,
            'line_kind' => 'service',
            'quantity' => '2.000',
            'conversion_factor' => '1.000000',
            'base_quantity' => '3.000',
            'unit_price' => '10.0000',
            'line_total' => '20.0000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $result = app(UnitIntegrityCheck::class)->run();

        expect(collect($result->mismatches)->contains(
            fn (array $item): bool => (int) ($item['document_line_id'] ?? 0) === $line
                && ($item['reason'] ?? '') === 'base_quantity_mismatch',
        ))->toBeTrue();
    });
});

it('flags document numbers that no longer match the series prefix and accounting year', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        app(GenerateDocumentNumber::class)->handle('sales_invoice', 2026);
        DB::connection('period')->table('documents')->insert([
            'document_type' => 'sales_invoice',
            'document_date' => '2026-10-10',
            'number' => 'CORRUPTED-V4',
            'status' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $result = app(NumberSeriesCheck::class)->run();

        expect(collect($result->mismatches)->contains(
            fn (array $item): bool => ($item['number'] ?? '') === 'CORRUPTED-V4'
                && ($item['reason'] ?? '') === 'format_mismatch',
        ))->toBeTrue();
    });
});

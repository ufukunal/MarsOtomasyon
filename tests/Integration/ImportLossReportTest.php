<?php

use App\Support\Reporting\Queries\ImportLossReportQuery;
use App\Support\Reporting\ReportExecutionContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\IsolatedPostgres;

it('reports imported SKU product-wide fire and scrap separately without inventing an import attribution', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        $db = DB::connection('period');
        $part = IsolatedPostgres::productAndLocation();
        $code = Str::random(9);
        $now = now();
        $contact = $db->table('contacts')->insertGetId([
            'title' => 'Supplier '.$code, 'type' => 'legal',
            'created_at' => $now, 'updated_at' => $now,
        ]);

        // Two containers belong to a single import; report one product row.
        $importId = $db->table('import_files')->insertGetId([
            'number' => 'LOSS-IMP-'.$code,
            'supplier_contact_id' => $contact, 'currency' => 'TRY',
            'exchange_rate' => 1, 'status' => 'received', 'received_at' => '2026-01-15',
            'created_at' => $now, 'updated_at' => $now,
        ]);

        foreach ([1, 2] as $partNumber) {
            $containerId = $db->table('containers')->insertGetId([
                'import_file_id' => $importId,
                'container_no' => 'LOSS-CNT-'.$partNumber.'-'.$code,
                'status' => 'received', 'created_at' => $now, 'updated_at' => $now,
            ]);
            $db->table('packages')->insert([
                'import_file_id' => $importId, 'container_id' => $containerId,
                'carton_no' => 'LOSS-PKG-'.$partNumber.'-'.$code,
                'product_id' => $part['product'], 'location_id' => $part['location'],
                'status' => 'received', 'quantity' => 100, 'unit_price' => 5,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        $recordOut = function (string $reason, int $quantity, ?string $documentType = null, ?int $documentId = null)
            use ($db, $part, $code, $now): int {
            return (int) $db->table('stock_movements')->insertGetId([
                'product_id' => $part['product'], 'location_id' => $part['location'],
                'product_code' => 'TEST-'.$code,
                'movement_date' => '2026-02-10', 'direction' => 'out', 'reason' => $reason,
                'quantity' => $quantity, 'unit_cost' => 5, 'total_cost' => $quantity * 5,
                'balance_after' => 200, 'avg_cost_after' => 5,
                'document_type' => $documentType, 'document_id' => $documentId,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        };

        // 10 sold, 12 production component loss/consumption, 3 scrapped on
        // posted warehouse slip, 1 quarantine scrap and 2 broken (not scrap).
        $recordOut('sale', 10);
        $productionOutId = $recordOut('production_consumption', 12);

        $recipeId = $db->table('production_recipes')->insertGetId([
            'product_id' => $part['product'], 'number' => 'LOSS-R-'.$code,
            'revision_no' => 1, 'output_quantity' => 1,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $orderId = $db->table('production_orders')->insertGetId([
            'number' => 'LOSS-O-'.$code, 'document_date' => '2026-02-10',
            'product_id' => $part['product'], 'recipe_id' => $recipeId,
            'recipe_revision_no' => 1, 'planned_quantity' => 1,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $completionId = $db->table('production_completions')->insertGetId([
            'production_order_id' => $orderId, 'completion_date' => '2026-02-10',
            'completed_quantity' => 1, 'material_cost_total' => 60,
            'production_cost_total' => 60, 'production_unit_cost' => 60,
            'moving_average_before' => 5, 'moving_average_after' => 5,
            'previous_production_cost' => 5,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $db->table('production_consumptions')->insert([
            'production_completion_id' => $completionId, 'component_product_id' => $part['product'],
            'location_id' => $part['location'], 'consumed_quantity' => 10,
            'fire_quantity' => 2, 'unit_cost' => 5, 'total_cost' => 60,
            'stock_movement_id' => $productionOutId,
            'created_at' => $now, 'updated_at' => $now,
        ]);

        $slipId = $db->table('warehouse_slips')->insertGetId([
            'number' => 'LOSS-S-'.$code, 'location_id' => $part['location'],
            'slip_date' => '2026-02-10', 'direction' => 'out',
            'reason' => 'scrap', 'status' => 'posted',
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $recordOut('adjustment', 3, 'warehouse_slip', $slipId);
        $db->table('warehouse_slip_lines')->insert([
            'warehouse_slip_id' => $slipId, 'product_id' => $part['product'],
            'quantity' => 3, 'created_at' => $now, 'updated_at' => $now,
        ]);

        $recordOut('scrap', 1, 'quarantine', 800);
        $brokenSlipId = $db->table('warehouse_slips')->insertGetId([
            'number' => 'LOSS-B-'.$code, 'location_id' => $part['location'],
            'slip_date' => '2026-02-10', 'direction' => 'out',
            'reason' => 'broken', 'status' => 'posted',
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $recordOut('adjustment', 2, 'warehouse_slip', $brokenSlipId);

        $report = new ImportLossReportQuery;
        $definition = $report->definition();
        $make = fn (int $quarter, array $extra = []): ReportExecutionContext => new ReportExecutionContext(
            filters: ['year' => 2026, 'quarter' => $quarter, ...$extra],
            columns: $definition->defaultColumnKeys(false),
            sort: $definition->defaultSort,
            totalKeys: [],
            limit: 20, offset: 0, canViewCost: false,
        );

        $res = $report->execute($make(1));
        expect($res->totalRows)->toBe(1)
            ->and($res->rows)->toHaveCount(1)
            ->and((float) $res->rows[0]['out_quantity'])->toBe(28.0)
            ->and((float) $res->rows[0]['fire_quantity'])->toBe(2.0)
            ->and((float) $res->rows[0]['scrap_quantity'])->toBe(4.0)
            ->and((float) $res->rows[0]['fire_rate'])->toBe(7.14)
            ->and((float) $res->rows[0]['scrap_rate'])->toBe(14.29)
            ->and($res->rows[0]['attribution_status'])->toBe('Ürün geneli; ithalata atanmadı');

        expect($report->execute($make(1, ['import_number' => 'LOSS-IMP-'.$code]))->totalRows)->toBe(1);
        expect($report->execute($make(1, ['import_number' => 'NOT-A-REAL-IMPORT-'.$code]))->totalRows)->toBe(0);

        $q2 = $report->execute($make(2));
        expect((float) $q2->rows[0]['out_quantity'])->toBe(0.0)
            ->and($q2->rows[0]['fire_rate'])->toBeNull()
            ->and($q2->rows[0]['scrap_rate'])->toBeNull();

        expect(fn () => $report->execute($make(1, ['year' => 2025])))
            ->toThrow(DomainException::class);
    });
});

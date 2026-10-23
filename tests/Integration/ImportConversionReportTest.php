<?php

use App\Actions\Imports\SaveImportReportingAllocation;
use App\Support\Reporting\Queries\ImportConversionReportQuery;
use App\Support\Reporting\ReportExecutionContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\AuthorizedPeriod;
use Tests\Support\IsolatedPostgres;

it('reports only verified container-attributed sales, returns and scrap without double counting', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();

        try {
            $db = DB::connection('period');
            $now = now();
            $code = Str::random(9);
            $fixture = IsolatedPostgres::productAndLocation();
            $contactId = $db->table('contacts')->insertGetId([
                'title' => 'Combined import customer '.$code, 'type' => 'legal',
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $importId = $db->table('import_files')->insertGetId([
                'number' => 'COMBINED-'.$code, 'supplier_contact_id' => $contactId,
                'currency' => 'TRY', 'exchange_rate' => 1,
                'status' => 'received', 'received_at' => '2026-01-15',
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $containerIds = [];

            foreach ([1, 2] as $index) {
                $containerId = $db->table('containers')->insertGetId([
                    'import_file_id' => $importId,
                    'container_no' => 'COMB-CNT-'.$index.'-'.$code,
                    'status' => 'received',
                    'created_at' => $now, 'updated_at' => $now,
                ]);
                $containerIds[] = $containerId;
                $db->table('packages')->insert([
                    'import_file_id' => $importId,
                    'container_id' => $containerId,
                    'carton_no' => 'COMB-PKG-'.$index.'-'.$code,
                    'product_id' => $fixture['product'],
                    'location_id' => $fixture['location'],
                    'quantity' => '100.000', 'unit_price' => '50.0000',
                    'status' => 'received',
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }

            $makeLine = function (string $type, string $number, string $date, int $qty, ?int $origin = null)
                use ($db, $now, $fixture, $contactId): int {
                $documentId = $db->table('documents')->insertGetId([
                    'document_type' => $type, 'number' => $number,
                    'document_date' => $date, 'contact_id' => $contactId,
                    'currency' => 'TRY', 'status' => 'posted',
                    'subtotal' => $qty * 100, 'tax_base' => $qty * 100,
                    'vat_amount' => $qty * 20, 'grand_total' => $qty * 120,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
                return $db->table('document_lines')->insertGetId([
                    'document_id' => $documentId, 'line_kind' => 'stock', 'line_no' => 1,
                    'product_id' => $fixture['product'], 'unit_id' => $fixture['unit'],
                    'location_id' => $fixture['location'], 'source_line_id' => $origin,
                    'quantity' => $qty, 'base_quantity' => $qty,
                    'conversion_factor' => 1, 'unit_price' => 100,
                    'line_total' => $qty * 100, 'vat_rate' => 20,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            };

            $invoiceLine = $makeLine('sales_invoice', 'S-COMB-'.$code, '2026-02-20', 100);
            $returnLine = $makeLine('sales_return', 'R-COMB-'.$code, '2026-03-03', 20, $invoiceLine);
            // Second invoice is NOT attributed, should never inflate group ratio.
            $makeLine('sales_invoice', 'UNMAPPED-'.$code, '2026-03-05', 30);

            $scrapId = $db->table('stock_movements')->insertGetId([
                'product_id' => $fixture['product'], 'location_id' => $fixture['location'],
                'product_code' => 'COMB-'.$code, 'movement_date' => '2026-03-05',
                'direction' => 'out', 'reason' => 'scrap',
                'quantity' => 10, 'unit_cost' => 5, 'total_cost' => 50,
                'balance_after' => 180, 'avg_cost_after' => 5,
                'document_type' => 'quarantine', 'document_id' => 1,
                'created_at' => $now, 'updated_at' => $now,
            ]);

            $action = app(SaveImportReportingAllocation::class);
            $save = fn (string $type, int $id, int $container, int $qty) => $action->handle([
                'source_type' => $type, 'source_id' => $id,
                'container_id' => $container, 'quantity' => (string) $qty,
                'note' => 'Warehouse manifest checked '.$code,
            ]);

            $saleA = $save('sales', $invoiceLine, $containerIds[0], 60);
            $save('sales', $invoiceLine, $containerIds[1], 40);
            $save('return', $returnLine, $containerIds[0], 20);
            $save('scrap', $scrapId, $containerIds[1], 10);

            expect(fn () => $save('sales', $invoiceLine, $containerIds[0], 1))
                ->toThrow(DomainException::class);
            expect(fn () => $save('return', $returnLine, $containerIds[1], 1))
                ->toThrow(DomainException::class);
            expect(fn () => $save('sales', $invoiceLine, $containerIds[1], 41))
                ->toThrow(DomainException::class);

            $report = new ImportConversionReportQuery;
            $definition = $report->definition();
            $query = function (array $filters = []) use ($report, $definition): \App\Support\Reporting\ReportQueryResult {
                return $report->execute(new ReportExecutionContext(
                    filters: ['year' => 2026, 'quarter' => 1, ...$filters],
                    columns: $definition->defaultColumnKeys(false),
                    sort: $definition->defaultSort,
                    totalKeys: [],
                    limit: 50, offset: 0, canViewCost: false,
                ));
            };

            $r = $query();
            expect($r->totalRows)->toBe(1)
                ->and((float) $r->rows[0]['import_quantity'])->toBe(200.0)
                ->and((float) $r->rows[0]['period_sales'])->toBe(80.0)
                ->and((float) $r->rows[0]['cumulative_sales'])->toBe(80.0)
                ->and((float) $r->rows[0]['conversion_rate'])->toBe(40.0)
                ->and((float) $r->rows[0]['cumulative_scrap'])->toBe(10.0)
                ->and((float) $r->rows[0]['scrap_rate'])->toBe(5.0)
                ->and((float) $r->rows[0]['unassigned_quantity'])->toBe(110.0);

            $containerReport = $query(['container_no' => 'COMB-CNT-1-'.$code]);
            expect($containerReport->totalRows)->toBe(1)
                ->and((float) $containerReport->rows[0]['import_quantity'])->toBe(100.0)
                ->and((float) $containerReport->rows[0]['cumulative_sales'])->toBe(40.0)
                ->and((float) $containerReport->rows[0]['conversion_rate'])->toBe(40.0);

            expect(fn () => $action->void($saleA, 'Recheck the manifest due to mistake'))
                ->toThrow(DomainException::class);

            $returnAllocationId = (int) $db->table('import_reporting_allocations')
                ->where('source_type', 'return')->value('id');
            $action->void($returnAllocationId, 'Correcting previously mapped return');
            $action->void($saleA, 'Correcting previously mapped sale');

            $after = $query();
            expect((float) $after->rows[0]['cumulative_sales'])->toBe(40.0)
                ->and((float) $after->rows[0]['conversion_rate'])->toBe(20.0)
                ->and($db->table('import_reporting_allocations')
                    ->whereNotNull('voided_at')->count())->toBe(2);

            $recipeId = $db->table('production_recipes')->insertGetId([
                'product_id' => $fixture['product'], 'number' => 'COMB-REC-'.$code,
                'revision_no' => 1, 'output_quantity' => 1,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $orderId = $db->table('production_orders')->insertGetId([
                'number' => 'COMB-ORDER-'.$code, 'document_date' => '2026-03-10',
                'product_id' => $fixture['product'], 'recipe_id' => $recipeId,
                'recipe_revision_no' => 1, 'planned_quantity' => 1,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $completionId = $db->table('production_completions')->insertGetId([
                'production_order_id' => $orderId, 'completion_date' => '2026-03-10',
                'completed_quantity' => 1, 'material_cost_total' => 25,
                'production_cost_total' => 25, 'production_unit_cost' => 25,
                'moving_average_before' => 5, 'moving_average_after' => 5,
                'previous_production_cost' => 5,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $movementId = $db->table('stock_movements')->insertGetId([
                'product_id' => $fixture['product'], 'location_id' => $fixture['location'],
                'product_code' => 'COMB-'.$code, 'movement_date' => '2026-03-10',
                'direction' => 'out', 'reason' => 'production_consumption',
                'quantity' => 5, 'unit_cost' => 5, 'total_cost' => 25,
                'balance_after' => 170, 'avg_cost_after' => 5,
                'document_type' => 'production_completion', 'document_id' => $completionId,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $consumptionId = $db->table('production_consumptions')->insertGetId([
                'production_completion_id' => $completionId,
                'component_product_id' => $fixture['product'],
                'location_id' => $fixture['location'], 'consumed_quantity' => 0,
                'fire_quantity' => 5, 'unit_cost' => 5, 'total_cost' => 25,
                'stock_movement_id' => $movementId,
                'created_at' => $now, 'updated_at' => $now,
            ]);

            $save('fire', $consumptionId, $containerIds[0], 5);
            $withFire = $query();
            expect((float) $withFire->rows[0]['cumulative_fire'])->toBe(5.0)
                ->and((float) $withFire->rows[0]['fire_rate'])->toBe(2.5);

            $db->table('production_completions')->insert([
                'production_order_id' => $orderId, 'completion_date' => '2026-03-20',
                'reversal_of_id' => $completionId, 'completed_quantity' => 1,
                'material_cost_total' => 25, 'production_cost_total' => 25,
                'production_unit_cost' => 25, 'moving_average_before' => 5,
                'moving_average_after' => 5, 'previous_production_cost' => 5,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $reversed = $query();
            expect((float) $reversed->rows[0]['cumulative_fire'])->toBe(0.0);
            expect(fn () => $save('fire', $consumptionId, $containerIds[0], 1))
                ->toThrow(DomainException::class);
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});

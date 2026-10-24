<?php

use App\Support\Reporting\Queries\SalesProductPerformanceReportQuery;
use App\Support\Reporting\Queries\StockCountDifferenceReportQuery;
use App\Support\Reporting\Queries\StockTransferReportQuery;
use App\Support\Reporting\Queries\WarehouseSlipReportQuery;
use App\Support\Reporting\ReportExecutionContext;
use App\Support\Reporting\ReportQueryResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\IsolatedPostgres;

function marsExpandedReportContext(object $query, array $filters = []): ReportExecutionContext
{
    $definition = $query->definition();

    return new ReportExecutionContext(
        filters: $filters,
        columns: $definition->defaultColumnKeys(false),
        sort: $definition->defaultSort,
        totalKeys: array_map(fn ($total): string => $total->key, $definition->totals),
        limit: 30, offset: 0, canViewCost: false,
    );
}

it('nets posted product invoices and returns without cross-currency summation', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        $db = DB::connection('period');
        $item = IsolatedPostgres::productAndLocation();
        $unique = Str::random(10);
        $contact = $db->table('contacts')->insertGetId([
            'title' => 'Reporting customer '.$unique, 'type' => 'legal',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $insert = function (string $type, string $currency, string $status, int $quantity)
            use ($db, $item, $contact, $unique): void {
            $subtotal = $quantity * 100;
            $id = $db->table('documents')->insertGetId([
                'document_type' => $type,
                'number' => $unique.'-'.$type.'-'.$currency.'-'.$status,
                'document_date' => '2026-05-10',
                'contact_id' => $contact,
                'status' => $status,
                'currency' => $currency,
                'subtotal' => $subtotal,
                'tax_base' => $subtotal,
                'vat_amount' => 0,
                'grand_total' => $subtotal,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $db->table('document_lines')->insert([
                'document_id' => $id, 'line_no' => 1, 'line_kind' => 'stock',
                'product_id' => $item['product'], 'unit_id' => $item['unit'],
                'quantity' => $quantity, 'base_quantity' => $quantity,
                'conversion_factor' => 1, 'unit_price' => 100,
                'line_total' => $subtotal, 'vat_rate' => 0,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        };

        $insert('sales_invoice', 'TRY', 'posted', 10);
        $insert('sales_return', 'TRY', 'posted', 2);
        $insert('sales_invoice', 'EUR', 'posted', 3);
        $insert('sales_invoice', 'TRY', 'draft', 50);

        $query = new SalesProductPerformanceReportQuery;
        $result = $query->execute(marsExpandedReportContext($query));

        expect($result->totalRows)->toBe(2);
        $byCurrency = collect($result->rows)->keyBy('currency');

        expect((float) $byCurrency['TRY']['sold_quantity'])->toBe(10.0)
            ->and((float) $byCurrency['TRY']['returned_quantity'])->toBe(2.0)
            ->and((float) $byCurrency['TRY']['net_quantity'])->toBe(8.0)
            ->and((float) $byCurrency['TRY']['net_amount'])->toBe(800.0)
            ->and((float) $byCurrency['EUR']['net_amount'])->toBe(300.0)
            ->and($result->totals['net_amount'])->toContain('EUR', 'TRY');

        $try = $query->execute(marsExpandedReportContext($query, ['currency' => 'TRY']));
        expect($try->totalRows)->toBe(1)
            ->and($try->totals['net_amount'])->toBe('800.0000');
    });
});

it('lists transfer receipts, posted count differences and warehouse slip lines independently', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        $db = DB::connection('period');
        $item = IsolatedPostgres::productAndLocation();
        $code = Str::random(12);
        $destination = $db->table('locations')->insertGetId([
            'code' => 'DEST-'.$code, 'name' => 'Destination '.$code, 'kind' => 'warehouse',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $transfer = $db->table('transfers')->insertGetId([
            'number' => 'TRANSFER-'.$code,
            'from_location_id' => $item['location'], 'to_location_id' => $destination,
            'transfer_date' => '2026-06-15',
            'status' => 'partially_received',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $db->table('transfer_lines')->insert([
            'transfer_id' => $transfer, 'product_id' => $item['product'],
            'quantity' => 10, 'received_quantity' => 6,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $countId = $db->table('stock_counts')->insertGetId([
            'number' => 'COUNT-'.$code, 'location_id' => $item['location'],
            'count_date' => '2026-06-16', 'status' => 'posted',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $db->table('stock_count_lines')->insert([
            'stock_count_id' => $countId, 'product_id' => $item['product'],
            'system_quantity' => 10, 'counted_quantity' => 8, 'difference' => -2,
            'is_approved' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $draftCount = $db->table('stock_counts')->insertGetId([
            'number' => 'DRAFT-'.$code, 'location_id' => $item['location'],
            'count_date' => '2026-06-17', 'status' => 'draft',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $db->table('stock_count_lines')->insert([
            'stock_count_id' => $draftCount, 'product_id' => $item['product'],
            'system_quantity' => 10, 'counted_quantity' => 1, 'difference' => -9,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $slipId = $db->table('warehouse_slips')->insertGetId([
            'number' => 'SLIP-'.$code, 'location_id' => $item['location'],
            'slip_date' => '2026-06-17', 'direction' => 'out',
            'reason' => 'scrap', 'status' => 'posted',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $db->table('warehouse_slip_lines')->insert([
            'warehouse_slip_id' => $slipId, 'product_id' => $item['product'],
            'quantity' => 4, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $transferQuery = new StockTransferReportQuery;
        $transferResult = $transferQuery->execute(marsExpandedReportContext($transferQuery));
        expect($transferResult->totalRows)->toBe(1)
            ->and((float) $transferResult->rows[0]['pending_quantity'])->toBe(4.0);

        $countQuery = new StockCountDifferenceReportQuery;
        $countResult = $countQuery->execute(marsExpandedReportContext($countQuery, ['difference_only' => true]));
        expect($countResult->totalRows)->toBe(1)
            ->and((float) $countResult->rows[0]['difference'])->toBe(-2.0);

        $slipQuery = new WarehouseSlipReportQuery;
        $slipResult = $slipQuery->execute(marsExpandedReportContext($slipQuery, ['status' => 'posted']));
        expect($slipResult->totalRows)->toBe(1)
            ->and((float) $slipResult->rows[0]['quantity'])->toBe(4.0);
    });
});

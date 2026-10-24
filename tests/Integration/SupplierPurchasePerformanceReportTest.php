<?php

use App\Support\Reporting\Queries\SupplierPurchasePerformanceReportQuery;
use App\Support\Reporting\ReportExecutionContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\IsolatedPostgres;

it('groups posted supplier invoices by supplier and currency without including drafts', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        $db = DB::connection('period');
        $suffix = Str::random(9);
        $supplier = $db->table('contacts')->insertGetId([
            'title' => 'Supplier analytics '.$suffix, 'type' => 'legal',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        foreach ([
            ['TRY', 'posted', 800],
            ['TRY', 'draft', 500],
            ['EUR', 'posted', 300],
        ] as [$currency, $status, $net]) {
            $db->table('documents')->insert([
                'number' => 'SUP-'.$suffix.'-'.$currency.'-'.$status,
                'document_type' => 'supplier_invoice',
                'document_date' => '2026-05-10',
                'contact_id' => $supplier,
                'status' => $status, 'currency' => $currency,
                'subtotal' => $net,
                'tax_base' => $net, 'vat_amount' => 0, 'grand_total' => $net,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $query = new SupplierPurchasePerformanceReportQuery;
        $definition = $query->definition();
        $run = fn (array $filters) => $query->execute(new ReportExecutionContext(
            filters: $filters,
            columns: $definition->defaultColumnKeys(false),
            sort: $definition->defaultSort,
            totalKeys: array_map(fn ($total): string => $total->key, $definition->totals),
            limit: 20, offset: 0, canViewCost: false,
        ));

        $result = $run(['contact_id' => $supplier]);
        expect($result->totalRows)->toBe(2);
        $rows = collect($result->rows)->keyBy('currency');
        expect((int) $rows['TRY']['invoice_count'])->toBe(1)
            ->and((float) $rows['TRY']['net_amount'])->toBe(800.0)
            ->and((float) $rows['EUR']['net_amount'])->toBe(300.0)
            ->and($result->totals['net_amount'])->toContain('TRY')
            ->and($result->totals['net_amount'])->toContain('EUR');

        $filtered = $run(['contact_id' => $supplier, 'currency' => 'TRY']);
        expect($filtered->totalRows)->toBe(1)
            ->and((float) $filtered->totals['net_amount'])->toBe(800.0);

        $future = $run(['date_from' => '2026-06-01', 'contact_id' => $supplier]);
        expect($future->totalRows)->toBe(0);
    });
});

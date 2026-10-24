<?php

use App\Support\Reporting\Queries\InactiveStockReportQuery;
use App\Support\Reporting\Queries\SalesCustomerPerformanceReportQuery;
use App\Support\Reporting\Queries\SecuritiesMaturityReportQuery;
use App\Support\Reporting\ReportExecutionContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\IsolatedPostgres;

function marsNewAnalyticsContext(object $query, array $filters = []): ReportExecutionContext
{
    $definition = $query->definition();

    return new ReportExecutionContext(
        filters: $filters,
        columns: $definition->defaultColumnKeys(false),
        sort: $definition->defaultSort,
        totalKeys: array_map(fn ($total): string => $total->key, $definition->totals),
        limit: 50,
        offset: 0,
        canViewCost: false,
    );
}

it('aggregates net customer invoices and returns without including drafts or mixing currencies', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        $db = DB::connection('period');
        $suffix = Str::random(10);
        $customer = $db->table('contacts')->insertGetId([
            'title' => 'Sales analytics '.$suffix, 'type' => 'legal',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        foreach ([
            ['sales_invoice', 'TRY', 'posted', 500],
            ['sales_return', 'TRY', 'posted', 100],
            ['sales_invoice', 'EUR', 'posted', 200],
            ['sales_invoice', 'TRY', 'draft', 1000],
        ] as [$type, $currency, $status, $amount]) {
            $db->table('documents')->insert([
                'number' => $suffix.'-'.$type.'-'.$currency.'-'.$status,
                'document_type' => $type,
                'contact_id' => $customer,
                'document_date' => '2026-04-15',
                'status' => $status,
                'currency' => $currency,
                'subtotal' => $amount,
                'tax_base' => $amount,
                'vat_amount' => 0,
                'grand_total' => $amount,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $query = new SalesCustomerPerformanceReportQuery;
        $result = $query->execute(marsNewAnalyticsContext($query, [
            'contact_id' => $customer, 'date_from' => '2026-04-01', 'date_to' => '2026-06-30',
        ]));
        expect($result->totalRows)->toBe(2);
        $rows = collect($result->rows)->keyBy('currency');

        expect((float) $rows['TRY']['invoice_net'])->toBe(500.0)
            ->and((float) $rows['TRY']['return_net'])->toBe(100.0)
            ->and((float) $rows['TRY']['net_sales'])->toBe(400.0)
            ->and((int) $rows['TRY']['invoice_count'])->toBe(1)
            ->and((int) $rows['TRY']['return_count'])->toBe(1)
            ->and((float) $rows['EUR']['net_sales'])->toBe(200.0)
            ->and($result->totals['net_sales'])->toContain('TRY')
            ->and($result->totals['net_sales'])->toContain('EUR');

        $filtered = $query->execute(marsNewAnalyticsContext($query, [
            'contact_id' => $customer, 'currency' => 'TRY',
        ]));
        expect($filtered->totalRows)->toBe(1)
            ->and((float) $filtered->totals['net_sales'])->toBe(400.0);
    });
});

it('finds current positive stock without recent movements by warehouse and threshold', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        $db = DB::connection('period');
        $fixture = IsolatedPostgres::productAndLocation();
        $past = now()->subDays(130)->toDateString();
        $db->table('stock_balances')->insert([
            'product_id' => $fixture['product'], 'location_id' => $fixture['location'],
            'quantity' => 20, 'reserved' => 3, 'quarantine' => 2,
            'consignment_reserved' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $db->table('stock_movements')->insert([
            'product_id' => $fixture['product'], 'location_id' => $fixture['location'],
            'product_code' => 'INACTIVE-'.Str::random(8),
            'movement_date' => $past,
            'direction' => 'in', 'reason' => 'opening',
            'quantity' => 20, 'unit_cost' => 0, 'total_cost' => 0,
            'balance_after' => 20, 'avg_cost_after' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $query = new InactiveStockReportQuery;
        $result = $query->execute(marsNewAnalyticsContext($query, [
            'product_id' => $fixture['product'], 'location_id' => $fixture['location'],
            'inactive_days' => 90,
        ]));
        expect($result->totalRows)->toBe(1)
            ->and((float) $result->rows[0]['quantity'])->toBe(20.0)
            ->and((float) $result->rows[0]['available'])->toBe(15.0)
            ->and((int) $result->rows[0]['inactive_days'])->toBeGreaterThanOrEqual(129);

        $freshThreshold = $query->execute(marsNewAnalyticsContext($query, [
            'product_id' => $fixture['product'], 'location_id' => $fixture['location'],
            'inactive_days' => 180,
        ]));
        expect($freshThreshold->totalRows)->toBe(0);
    });
});

it('shows security maturity relative to selected date while retaining payment status and currency', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        $db = DB::connection('period');
        $suffix = Str::random(11);
        $contact = $db->table('contacts')->insertGetId([
            'title' => 'Securities analytics '.$suffix,
            'type' => 'legal',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach ([
            ['TRY', 'portfolio', '2026-10-20', 120],
            ['EUR', 'collected', '2026-11-02', 50],
        ] as [$currency, $status, $due, $amount]) {
            $db->table('securities')->insert([
                'fingerprint' => hash('sha256', $suffix.'-'.$currency),
                'instrument_no' => $suffix.'-'.$currency,
                'contact_id' => $contact,
                'direction' => 'incoming', 'kind' => 'check',
                'status' => $status, 'currency' => $currency,
                'due_date' => $due, 'amount' => $amount,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $query = new SecuritiesMaturityReportQuery;
        $report = $query->execute(marsNewAnalyticsContext($query, [
            'contact_id' => $contact, 'as_of' => '2026-10-24',
        ]));
        expect($report->totalRows)->toBe(2);
        $rows = collect($report->rows)->keyBy('currency');

        expect((int) $rows['TRY']['days_to_due'])->toBe(-4)
            ->and($rows['TRY']['due_group'])->toBe('Geçmiş tarih')
            ->and((int) $rows['EUR']['days_to_due'])->toBe(9)
            ->and($rows['EUR']['due_group'])->toBe('8-30 gün')
            ->and($rows['EUR']['status'])->toBe('collected')
            ->and($report->totals['amount'])->toContain('TRY')
            ->and($report->totals['amount'])->toContain('EUR');

        $onlyTry = $query->execute(marsNewAnalyticsContext($query, [
            'contact_id' => $contact, 'currency' => 'TRY',
        ]));
        expect($onlyTry->totalRows)->toBe(1)
            ->and((float) $onlyTry->totals['amount'])->toBe(120.0);
    });
});

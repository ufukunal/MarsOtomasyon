<?php

namespace App\Support\Reporting\Queries;

use App\Contracts\Reporting\ReportQuery;
use App\Support\Period\PeriodContext;
use App\Support\Reporting\ReportColumnDefinition;
use App\Support\Reporting\ReportDefinition;
use App\Support\Reporting\ReportExecutionContext;
use App\Support\Reporting\ReportFilterDefinition;
use App\Support\Reporting\ReportQueryResult;
use App\Support\Reporting\ReportSort;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Conservative, source-verified import/container metrics. This is NOT a
 * company-wide SKU estimate and never calculates partnership earnings.
 */
final class ImportConversionReportQuery implements ReportQuery
{
    public function definition(): ReportDefinition
    {
        return new ReportDefinition(
            key: 'imports.conversion',
            title: 'Birleşik İthalat Dönüşüm ve Fire / Hurda',
            category: 'İthalat',
            permission: 'import_shipments.view',
            filters: [
                new ReportFilterDefinition('year', 'Yıl', 'positive_integer'),
                new ReportFilterDefinition('quarter', 'Çeyrek', 'select', allowedValues: [1, 2, 3, 4]),
                new ReportFilterDefinition('import_number', 'İthalat Dosyası'),
                new ReportFilterDefinition('container_no', 'Konteyner No'),
                new ReportFilterDefinition('product_code', 'Ürün Kodu'),
            ],
            columns: [
                new ReportColumnDefinition('id', 'İthalat ID', 'integer', defaultVisible: false),
                new ReportColumnDefinition('import_number', 'Birleşik İthalat'),
                new ReportColumnDefinition('product_code', 'Ürün Kodu'),
                new ReportColumnDefinition('product_name', 'Ürün Adı'),
                new ReportColumnDefinition('unit_name', 'Temel Birim'),
                new ReportColumnDefinition('import_quantity', 'İthal Miktar', 'quantity'),
                new ReportColumnDefinition('period_sales', 'Çeyrek Net Satış', 'quantity'),
                new ReportColumnDefinition('cumulative_sales', 'Birikimli Net Satış', 'quantity'),
                new ReportColumnDefinition('conversion_rate', 'Satış Dönüşüm (%)', 'decimal'),
                new ReportColumnDefinition('period_fire', 'Çeyrek Fire', 'quantity'),
                new ReportColumnDefinition('cumulative_fire', 'Birikimli Fire', 'quantity'),
                new ReportColumnDefinition('fire_rate', 'Fire Oranı (%)', 'decimal'),
                new ReportColumnDefinition('period_scrap', 'Çeyrek Hurda', 'quantity'),
                new ReportColumnDefinition('cumulative_scrap', 'Birikimli Hurda', 'quantity'),
                new ReportColumnDefinition('scrap_rate', 'Hurda Oranı (%)', 'decimal'),
                new ReportColumnDefinition('unassigned_quantity', 'Eşleştirilmemiş / Diğer Miktar', 'quantity'),
                new ReportColumnDefinition('attribution_status', 'Kaynak Durumu', sortable: false),
            ],
            defaultSort: [new ReportSort('import_number'), new ReportSort('product_code')],
        );
    }

    public function execute(ReportExecutionContext $context): ReportQueryResult
    {
        $activeYear = PeriodContext::year();
        $year = (int) ($context->filters['year'] ?? $activeYear ?? now()->year);
        $quarter = (int) ($context->filters['quarter'] ?? intdiv((int) now()->month - 1, 3) + 1);

        if ($year < 2000 || $year > 2100 || $quarter < 1 || $quarter > 4) {
            throw new DomainException('İthalat dönüşüm raporunun yıl veya çeyreği geçersiz.');
        }
        if ($activeYear !== null && $year !== $activeYear) {
            throw new DomainException('Rapor yılı seçili muhasebe döneminden farklı.');
        }

        $periodStart = CarbonImmutable::create($year, ($quarter - 1) * 3 + 1, 1);
        $from = $periodStart->toDateString();
        $to = $periodStart->addMonths(3)->subDay()->toDateString();
        $db = DB::connection('period');

        // One product quantity per combined import, optionally scoped to one
        // of its containers. A SKU is NEVER combined with another import.
        $inbound = $db->table('packages as pk')
            ->join('import_files as f', 'f.id', '=', 'pk.import_file_id')
            ->join('containers as ct', 'ct.id', '=', 'pk.container_id')
            ->whereIn('f.status', ['received', 'closed'])
            ->where('f.received_at', '<=', $to)
            ->where('pk.status', 'received')
            ->whereNotNull('pk.product_id');

        $this->filters($inbound, $context);

        $inbound->selectRaw(
            'pk.import_file_id, pk.product_id, SUM(pk.quantity) AS import_quantity'
        )->groupBy('pk.import_file_id', 'pk.product_id');

        $assignments = $db->table('import_reporting_allocations as a')
            ->join('containers as ct', 'ct.id', '=', 'a.container_id')
            ->leftJoin('document_lines as dl', function ($join): void {
                $join->on('dl.id', '=', 'a.source_id')->whereIn('a.source_type', ['sales', 'return']);
            })
            ->leftJoin('documents as d', 'd.id', '=', 'dl.document_id')
            ->leftJoin('production_consumptions as pc', function ($join): void {
                $join->on('pc.id', '=', 'a.source_id')->where('a.source_type', '=', 'fire');
            })
            ->leftJoin('production_completions as completion', 'completion.id', '=', 'pc.production_completion_id')
            ->leftJoin('stock_movements as sm', function ($join): void {
                $join->on('sm.id', '=', 'a.source_id')->where('a.source_type', '=', 'scrap');
            })
            ->leftJoin('warehouse_slips as ws', function ($join): void {
                $join->on('ws.id', '=', 'sm.document_id')->where('sm.document_type', '=', 'warehouse_slip');
            })
            ->whereNull('a.voided_at')
            ->where('a.event_date', '<=', $to)
            ->where(function (Builder $query): void {
                $query->where(function (Builder $sales): void {
                    $sales->whereIn('a.source_type', ['sales', 'return'])
                        ->where('d.status', 'posted')
                        ->whereNotExists(function (Builder $rev): void {
                            $rev->selectRaw('1')->from('document_relations as r')
                                ->where('r.relation_type', 'reversal_of')
                                ->where(function (Builder $group): void {
                                    $group->whereColumn('r.source_document_id', 'd.id')
                                        ->orWhereColumn('r.target_document_id', 'd.id');
                                });
                        });
                })->orWhere(function (Builder $fire): void {
                    $fire->where('a.source_type', 'fire')
                        ->whereNotNull('completion.id')
                        ->whereNull('completion.reversal_of_id')
                        ->whereNotExists(function (Builder $rev): void {
                            $rev->selectRaw('1')->from('production_completions as reversal')
                                ->whereColumn('reversal.reversal_of_id', 'completion.id');
                        });
                })->orWhere(function (Builder $scrap): void {
                    $scrap->where('a.source_type', 'scrap')
                        ->where('sm.direction', 'out')
                        ->where(function (Builder $kind): void {
                            $kind->where('sm.reason', 'scrap')
                                ->orWhere(function (Builder $slip): void {
                                    $slip->where('sm.reason', 'adjustment')
                                        ->where('sm.document_type', 'warehouse_slip')
                                        ->where('ws.reason', 'scrap')
                                        ->where('ws.direction', 'out');
                                });
                        })
                        ->where(function (Builder $state): void {
                            $state->whereNull('ws.id')->orWhere('ws.status', 'posted');
                        });
                });
            });

        if (isset($context->filters['container_no'])) {
            $assignments->whereRaw('UPPER(ct.container_no) = UPPER(?)', [$context->filters['container_no']]);
        }

        $signedSales = "(CASE WHEN a.source_type = 'sales' THEN a.quantity WHEN a.source_type = 'return' THEN -a.quantity ELSE 0 END)";
        $fire = "(CASE WHEN a.source_type = 'fire' THEN a.quantity ELSE 0 END)";
        $scrap = "(CASE WHEN a.source_type = 'scrap' THEN a.quantity ELSE 0 END)";
        $whenPeriod = "a.event_date BETWEEN ? AND ?";

        $assignments->selectRaw(
            "a.import_file_id, a.product_id,
             SUM($signedSales) AS cumulative_sales,
             SUM($fire) AS cumulative_fire,
             SUM($scrap) AS cumulative_scrap,
             SUM(CASE WHEN $whenPeriod THEN $signedSales ELSE 0 END) AS period_sales,
             SUM(CASE WHEN $whenPeriod THEN $fire ELSE 0 END) AS period_fire,
             SUM(CASE WHEN $whenPeriod THEN $scrap ELSE 0 END) AS period_scrap,
             COUNT(a.id) AS mapped_count",
            [$from, $to, $from, $to, $from, $to],
        )->groupBy('a.import_file_id', 'a.product_id');

        $rows = $db->query()
            ->fromSub($inbound, 'inbound')
            ->join('import_files as f', 'f.id', '=', 'inbound.import_file_id')
            ->join('products as p', 'p.id', '=', 'inbound.product_id')
            ->leftJoin('units as u', 'u.id', '=', 'p.unit_id')
            ->leftJoinSub($assignments, 'm', function ($join): void {
                $join->on('m.import_file_id', '=', 'inbound.import_file_id')
                    ->on('m.product_id', '=', 'inbound.product_id');
            });

        if (isset($context->filters['product_code'])) {
            $rows->whereRaw('UPPER(p.code) = UPPER(?)', [$context->filters['product_code']]);
        }
        $totalRows = (clone $rows)->count();

        $qty = 'inbound.import_quantity';
        $sale = 'COALESCE(m.cumulative_sales,0)';
        $fireQty = 'COALESCE(m.cumulative_fire,0)';
        $scrapQty = 'COALESCE(m.cumulative_scrap,0)';
        $remaining = "($qty - $sale - $fireQty - $scrapQty)";

        $rate = static fn (string $numerator): string =>
            "(CASE WHEN $qty > 0 THEN ROUND($numerator * 100 / $qty, 2) ELSE NULL END)";

        $numeric = [
            'id' => 'f.id',
            'import_number' => 'f.number',
            'product_code' => 'p.code',
            'product_name' => 'p.name',
            'unit_name' => 'u.name',
            'import_quantity' => $qty,
            'period_sales' => 'COALESCE(m.period_sales,0)',
            'cumulative_sales' => $sale,
            'conversion_rate' => $rate($sale),
            'period_fire' => 'COALESCE(m.period_fire,0)',
            'cumulative_fire' => $fireQty,
            'fire_rate' => $rate($fireQty),
            'period_scrap' => 'COALESCE(m.period_scrap,0)',
            'cumulative_scrap' => $scrapQty,
            'scrap_rate' => $rate($scrapQty),
            'unassigned_quantity' => $remaining,
        ];

        foreach ($context->columns as $key) {
            if ($key === 'attribution_status') {
                $rows->selectRaw("(CASE WHEN COALESCE(m.mapped_count,0) > 0
                    THEN 'Kısmi / doğrulanmış eşleştirmeler' ELSE 'Henüz eşleştirme yok' END) AS attribution_status");
            } elseif (in_array($key, ['id'], true)) {
                $rows->selectRaw($numeric[$key].' AS '.$key);
            } elseif (in_array($key, ['import_number', 'product_code', 'product_name', 'unit_name'], true)) {
                $rows->selectRaw($numeric[$key].' AS '.$key);
            } else {
                $rows->selectRaw('('.$numeric[$key].')::text AS '.$key);
            }
        }

        foreach ($context->sort as $sort) {
            $rows->orderByRaw($numeric[$sort->key].' '.$sort->direction);
        }
        $rows->orderBy('f.id')->orderBy('p.id');

        return new ReportQueryResult(
            rows: $rows->offset($context->offset)->limit($context->limit)
                ->get()->map(fn (object $row): array => (array) $row)->all(),
            totals: [],
            totalRows: $totalRows,
        );
    }

    private function filters(Builder $query, ReportExecutionContext $context): void
    {
        if (isset($context->filters['import_number'])) {
            $query->whereRaw('UPPER(f.number) = UPPER(?)', [$context->filters['import_number']]);
        }
        if (isset($context->filters['container_no'])) {
            $query->whereRaw('UPPER(ct.container_no) = UPPER(?)', [$context->filters['container_no']]);
        }
        if (isset($context->filters['product_code'])) {
            $query->whereRaw('UPPER(pk.product_id::text) IN (
                SELECT UPPER(product.id::text) FROM products AS product WHERE UPPER(product.code) = UPPER(?)
            )', [$context->filters['product_code']]);
        }
    }
}

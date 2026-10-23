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
 * Loss indicators for product codes appearing in received imports.
 *
 * Warehouse slips, quarantine scrap and production consumption do not identify
 * the source import lot. These are PRODUCT-WIDE outflow shares, never true
 * import-file/container loss percentages. No allocation is inferred.
 */
final class ImportLossReportQuery implements ReportQuery
{
    public function definition(): ReportDefinition
    {
        return new ReportDefinition(
            key: 'imports.loss',
            title: 'İthalat Ürünleri Fire / Hurda İzleme',
            category: 'İthalat',
            permission: 'import_shipments.view',
            filters: [
                new ReportFilterDefinition('year', 'Yıl', 'positive_integer'),
                new ReportFilterDefinition('quarter', 'Çeyrek', 'select', allowedValues: [1, 2, 3, 4]),
                new ReportFilterDefinition('import_number', 'Olası İthalat Dosya No'),
                new ReportFilterDefinition('product_code', 'Ürün Kodu'),
            ],
            columns: [
                new ReportColumnDefinition('product_code', 'Ürün Kodu'),
                new ReportColumnDefinition('product_name', 'Ürün Adı'),
                new ReportColumnDefinition('unit_name', 'Temel Birim'),
                new ReportColumnDefinition('out_quantity', 'Ürün Geneli Stok Çıkışı', 'quantity'),
                new ReportColumnDefinition('fire_quantity', 'Üretim Firesi', 'quantity'),
                new ReportColumnDefinition('fire_rate', 'Ürün Geneli Fire Payı (%)', 'decimal'),
                new ReportColumnDefinition('scrap_quantity', 'Hurda Çıkışı', 'quantity'),
                new ReportColumnDefinition('scrap_rate', 'Ürün Geneli Hurda Payı (%)', 'decimal'),
                new ReportColumnDefinition('attribution_status', 'İthalat Kaynak Eşleşmesi', sortable: false),
            ],
            defaultSort: [new ReportSort('product_code')],
        );
    }

    public function execute(ReportExecutionContext $context): ReportQueryResult
    {
        $activeYear = PeriodContext::year();
        $year = (int) ($context->filters['year'] ?? $activeYear ?? now()->year);
        $quarter = (int) ($context->filters['quarter'] ?? intdiv(now()->month - 1, 3) + 1);

        if ($year < 2000 || $year > 2100 || $quarter < 1 || $quarter > 4) {
            throw new DomainException('Fire/hurda raporu yıl veya çeyrek bilgisi geçersiz.');
        }
        if ($activeYear !== null && $year !== $activeYear) {
            throw new DomainException('Seçilen yıl aktif çalışma döneminden farklı.');
        }

        $start = CarbonImmutable::create($year, ($quarter - 1) * 3 + 1, 1);
        $from = $start->toDateString();
        $to = $start->addMonths(3)->subDay()->toDateString();
        $db = DB::connection('period');

        // A single candidate SKU appears once, even if many containers/files
        // contain the product. Import filters narrow candidate SKUs only.
        $base = $db->table('products as p')
            ->leftJoin('units as u', 'u.id', '=', 'p.unit_id')
            ->whereExists(function (Builder $query) use ($context, $to): void {
                $query->selectRaw('1')->from('packages as pk')
                    ->join('import_files as f', 'f.id', '=', 'pk.import_file_id')
                    ->whereColumn('pk.product_id', 'p.id')
                    ->whereIn('f.status', ['received', 'closed'])
                    ->where('f.received_at', '<=', $to);

                if (isset($context->filters['import_number'])) {
                    $query->whereRaw('UPPER(f.number) = UPPER(?)', [$context->filters['import_number']]);
                }
            });

        if (isset($context->filters['product_code'])) {
            $base->whereRaw('UPPER(p.code) = UPPER(?)', [$context->filters['product_code']]);
        }

        // Product-wide physical outflow. Ignore explicit reversing movements;
        // transfers and other outflows remain in the stated denominator.
        $outflow = $db->table('stock_movements as sm')
            ->where('sm.direction', 'out')
            ->where('sm.reason', '<>', 'reversal')
            ->whereBetween('sm.movement_date', [$from, $to])
            ->whereNotExists(function (Builder $query): void {
                $query->selectRaw('1')->from('warehouse_slips as cancelled_slip')
                    ->whereColumn('cancelled_slip.id', 'sm.document_id')
                    ->where('sm.document_type', 'warehouse_slip')
                    ->where('cancelled_slip.status', 'cancelled');
            })
            ->whereNotExists(function (Builder $query): void {
                $query->selectRaw('1')->from('production_consumptions as reversed_consumption')
                    ->join('production_completions as original', 'original.id', '=', 'reversed_consumption.production_completion_id')
                    ->join('production_completions as reversal', 'reversal.reversal_of_id', '=', 'original.id')
                    ->whereColumn('reversed_consumption.stock_movement_id', 'sm.id');
            })
            ->selectRaw('sm.product_id, SUM(sm.quantity) AS quantity')
            ->groupBy('sm.product_id');

        // Component-level production fire; reversed completions are excluded.
        $fire = $db->table('production_consumptions as pc')
            ->join('production_completions as completion', 'completion.id', '=', 'pc.production_completion_id')
            ->whereNull('completion.reversal_of_id')
            ->whereNotExists(function (Builder $query): void {
                $query->selectRaw('1')->from('production_completions as reversal')
                    ->whereColumn('reversal.reversal_of_id', 'completion.id');
            })
            ->whereBetween('completion.completion_date', [$from, $to])
            ->selectRaw('pc.component_product_id AS product_id, SUM(pc.fire_quantity) AS quantity')
            ->groupBy('pc.component_product_id');

        // Quarantine scrap uses reason=scrap. A posted warehouse slip with
        // reason=scrap records its stock movement as reason=adjustment instead.
        $scrap = $db->table('stock_movements as sm')
            ->leftJoin('warehouse_slips as ws', function ($join): void {
                $join->on('ws.id', '=', 'sm.document_id')
                    ->where('sm.document_type', '=', 'warehouse_slip');
            })
            ->where('sm.direction', 'out')
            ->whereBetween('sm.movement_date', [$from, $to])
            ->where(function (Builder $query): void {
                $query->where('sm.reason', 'scrap')
                    ->orWhere(function (Builder $nested): void {
                        $nested->where('sm.document_type', 'warehouse_slip')
                            ->where('ws.status', 'posted')
                            ->where('ws.direction', 'out')
                            ->where('ws.reason', 'scrap');
                    });
            })
            ->selectRaw('sm.product_id, SUM(sm.quantity) AS quantity')
            ->groupBy('sm.product_id');

        $base
            ->leftJoinSub($outflow, 'outflow', 'outflow.product_id', '=', 'p.id')
            ->leftJoinSub($fire, 'fire', 'fire.product_id', '=', 'p.id')
            ->leftJoinSub($scrap, 'scrap', 'scrap.product_id', '=', 'p.id');

        $totalRows = (clone $base)->count('p.id');

        $out = 'COALESCE(outflow.quantity, 0)';
        $lossFire = 'COALESCE(fire.quantity, 0)';
        $lossScrap = 'COALESCE(scrap.quantity, 0)';
        $ratesNumeric = [
            'fire_rate' => "(CASE WHEN $out > 0 THEN ROUND($lossFire * 100 / $out, 2) ELSE NULL END)",
            'scrap_rate' => "(CASE WHEN $out > 0 THEN ROUND($lossScrap * 100 / $out, 2) ELSE NULL END)",
        ];
        $rates = [
            'fire_rate' => $ratesNumeric['fire_rate'].'::text',
            'scrap_rate' => $ratesNumeric['scrap_rate'].'::text',
        ];
        $fields = [
            'product_code' => 'p.code',
            'product_name' => 'p.name',
            'unit_name' => 'u.name',
            'out_quantity' => "$out::text",
            'fire_quantity' => "$lossFire::text",
            'fire_rate' => $rates['fire_rate'],
            'scrap_quantity' => "$lossScrap::text",
            'scrap_rate' => $rates['scrap_rate'],
            'attribution_status' => "'Ürün geneli; ithalata atanmadı'",
        ];
        $sort = [
            'product_code' => 'p.code',
            'product_name' => 'p.name',
            'unit_name' => 'u.name',
            'out_quantity' => $out,
            'fire_quantity' => $lossFire,
            'fire_rate' => $ratesNumeric['fire_rate'],
            'scrap_quantity' => $lossScrap,
            'scrap_rate' => $ratesNumeric['scrap_rate'],
        ];

        $rows = clone $base;
        foreach ($context->columns as $key) {
            $rows->selectRaw($fields[$key].' AS '.$key);
        }
        foreach ($context->sort as $item) {
            $rows->orderByRaw($sort[$item->key].' '.$item->direction);
        }
        $rows->orderBy('p.id');

        return new ReportQueryResult(
            rows: $rows->offset($context->offset)->limit($context->limit)
                ->get()->map(fn (object $row): array => (array) $row)->all(),
            totals: [],
            totalRows: $totalRows,
        );
    }
}

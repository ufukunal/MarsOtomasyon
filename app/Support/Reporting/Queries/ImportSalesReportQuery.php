<?php

namespace App\Support\Reporting\Queries;

use App\Contracts\Reporting\ReportQuery;
use App\Enums\DocumentType;
use App\Support\Reporting\CurrencySeparatedTotals;
use App\Support\Reporting\ReportColumnDefinition;
use App\Support\Reporting\ReportDefinition;
use App\Support\Reporting\ReportExecutionContext;
use App\Support\Reporting\ReportFilterDefinition;
use App\Support\Reporting\ReportQueryResult;
use App\Support\Reporting\ReportSort;
use App\Support\Reporting\ReportTotalDefinition;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Invoice-line export for products received through import files.
 *
 * Import lots are NOT persisted on sales document lines. A product can belong
 * to several import files or also have domestic stock; therefore the import
 * numbers are possible sources, not a claim of precise lot attribution.
 */
final class ImportSalesReportQuery implements ReportQuery
{
    public function definition(): ReportDefinition
    {
        return new ReportDefinition(
            key: 'imports.sales',
            title: 'İthalat Ürün Satışları (3 Aylık)',
            category: 'İthalat',
            permission: 'import_shipments.view',
            filters: [
                new ReportFilterDefinition('year', 'Yıl', 'positive_integer'),
                new ReportFilterDefinition('quarter', 'Çeyrek', 'select', allowedValues: [1, 2, 3, 4]),
                new ReportFilterDefinition('import_file_id', 'İthalat Dosyası ID', 'positive_integer'),
                new ReportFilterDefinition('product_id', 'Ürün ID', 'positive_integer'),
                new ReportFilterDefinition('contact_id', 'Cari ID', 'positive_integer'),
                new ReportFilterDefinition('currency', 'Döviz'),
            ],
            columns: [
                new ReportColumnDefinition('id', 'Belge ID', 'integer', defaultVisible: false),
                new ReportColumnDefinition('line_id', 'Satır ID', 'integer', defaultVisible: false),
                new ReportColumnDefinition('document_date', 'Fatura / İade Tarihi', 'date'),
                new ReportColumnDefinition('number', 'Fatura / İade No'),
                new ReportColumnDefinition('document_type', 'İşlem'),
                new ReportColumnDefinition('contact_code', 'Cari Kod'),
                new ReportColumnDefinition('contact_title', 'Müşteri'),
                new ReportColumnDefinition('product_code', 'Ürün Kod'),
                new ReportColumnDefinition('product_name', 'Ürün Adı'),
                new ReportColumnDefinition('possible_import_files', 'Olası İthalat Dosyaları', sortable: false),
                new ReportColumnDefinition('quantity', 'Miktar (+Satış / -İade)', 'quantity'),
                new ReportColumnDefinition('unit_name', 'Birim'),
                new ReportColumnDefinition('unit_price', 'Birim Fiyat', 'decimal'),
                new ReportColumnDefinition('line_discount_amount', 'Satır İskontosu', 'decimal'),
                new ReportColumnDefinition('net_amount', 'KDV Hariç Net Tutar', 'decimal'),
                new ReportColumnDefinition('vat_amount', 'Hesaplanan KDV', 'decimal'),
                new ReportColumnDefinition('grand_total', 'KDV Dahil Satır Tutarı', 'decimal'),
                new ReportColumnDefinition('currency', 'Döviz'),
            ],
            defaultSort: [
                new ReportSort('document_date'),
                new ReportSort('line_id'),
            ],
            totals: [
                new ReportTotalDefinition('net_amount', 'KDV Hariç Net Satış'),
                new ReportTotalDefinition('vat_amount', 'Hesaplanan KDV'),
                new ReportTotalDefinition('grand_total', 'KDV Dahil Satır Tutarı'),
            ],
       );
    }

    public function execute(ReportExecutionContext $context): ReportQueryResult
    {
        $year = (int) ($context->filters['year'] ?? now()->year);
        $quarter = (int) ($context->filters['quarter'] ?? intdiv(now()->month - 1, 3) + 1);

        if ($year < 2000 || $year > 2100 || $quarter < 1 || $quarter > 4) {
            throw new DomainException('İthalat satış raporunun yılı veya çeyreği geçersiz.');
        }

        $from = CarbonImmutable::create($year, ($quarter - 1) * 3 + 1, 1)->toDateString();
        $to = CarbonImmutable::create($year, ($quarter - 1) * 3 + 1, 1)
            ->addMonths(3)->subDay()->toDateString();

        $base = DB::connection('period')->table('document_lines as l')
            ->join('documents as d', 'd.id', '=', 'l.document_id')
            ->join('products as p', 'p.id', '=', 'l.product_id')
            ->leftJoin('contacts as c', 'c.id', '=', 'd.contact_id')
            ->leftJoin('units as u', 'u.id', '=', 'l.unit_id')
            ->where('l.line_kind', 'stock')
            ->where('d.status', 'posted')
            ->whereIn('d.document_type', [
                DocumentType::SalesInvoice->value,
                DocumentType::SalesReturn->value,
            ])
            ->whereBetween('d.document_date', [$from, $to])
            ->whereNotExists(function (Builder $query): void {
                $query->selectRaw('1')->from('document_relations as r')
                    ->where('r.relation_type', 'reversal_of')
                    ->where(function (Builder $related): void {
                        $related->whereColumn('r.source_document_id', 'd.id')
                            ->orWhereColumn('r.target_document_id', 'd.id');
                    });
            })
            ->whereExists(function (Builder $query) use ($context): void {
                $query->selectRaw('1')
                    ->from('packages as ip')
                    ->join('import_files as f', 'f.id', '=', 'ip.import_file_id')
                    ->whereColumn('ip.product_id', 'l.product_id')
                    ->whereIn('f.status', ['received', 'closed'])
                    ->whereColumn('f.received_at', '<=', 'd.document_date');

                if (isset($context->filters['import_file_id'])) {
                    $query->where('f.id', $context->filters['import_file_id']);
                }
            });

        if (isset($context->filters['product_id'])) {
            $base->where('l.product_id', $context->filters['product_id']);
        }
        if (isset($context->filters['contact_id'])) {
            $base->where('d.contact_id', $context->filters['contact_id']);
        }
        if (isset($context->filters['currency'])) {
            $base->where('d.currency', strtoupper((string) $context->filters['currency']));
        }

        $totalRows = (clone $base)->count('l.id');
        $rows = clone $base;

        if (! $context->hasColumn('id')) {
            $rows->selectRaw('d.id AS id');
        }
        if (! $context->hasColumn('line_id')) {
            $rows->selectRaw('l.id AS line_id');
        }

        foreach ($context->columns as $column) {
            $rows->selectRaw($this->expressions()[$column].' AS '.$column);
        }

        $sortKeys = [];
        foreach ($context->sort as $sort) {
            $rows->orderByRaw($this->sortExpressions()[$sort->key].' '.$sort->direction);
            $sortKeys[$sort->key] = true;
        }
        if (! isset($sortKeys['line_id'])) {
            $rows->orderBy('l.id');
        }

        return new ReportQueryResult(
            rows: $rows->offset($context->offset)->limit($context->limit)->get()
                ->map(fn (object $row): array => (array) $row)->all(),
            totals: $this->totals($base, $context),
            totalRows: $totalRows,
        );
    }

    /** @return array<string,string> */
    private function expressions(): array
    {
        // Include invoice-level additional discount via tax_base / subtotal.
        $netBeforeSign = '(CASE WHEN d.subtotal > 0 THEN ROUND(l.line_total * d.tax_base / d.subtotal, 4) ELSE l.line_total END)';
        $sign = "(CASE WHEN d.document_type = 'sales_return' THEN -1 ELSE 1 END)";
        $net = "($sign * $netBeforeSign)";
        $vat = "ROUND($net * l.vat_rate / 100, 4)";

        return [
            'id' => 'd.id',
            'line_id' => 'l.id',
            'document_date' => 'd.document_date::text',
            'number' => 'd.number',
            'document_type' => 'd.document_type',
            'contact_code' => 'c.code',
            'contact_title' => 'c.title',
            'product_code' => 'p.code',
            'product_name' => 'p.name',
            'possible_import_files' => "(SELECT STRING_AGG(DISTINCT f.number, ', ' ORDER BY f.number)
                FROM packages AS ip JOIN import_files AS f ON f.id = ip.import_file_id
                WHERE ip.product_id = l.product_id AND f.status IN ('received','closed')
                    AND f.received_at <= d.document_date)",
            'quantity' => "($sign * l.quantity)::text",
            'unit_name' => 'u.name',
            'unit_price' => 'l.unit_price::text',
            'line_discount_amount' => 'l.line_discount_amount::text',
            'net_amount' => "$net::text",
            'vat_amount' => "$vat::text",
            'grand_total' => "($net + $vat)::text",
            'currency' => 'd.currency',
        ];
    }

    /** @return array<string,string> */
    private function sortExpressions(): array
    {
        return [
            'id' => 'd.id',
            'line_id' => 'l.id',
            'document_date' => 'd.document_date',
            'number' => 'd.number',
            'document_type' => 'd.document_type',
            'contact_code' => 'c.code',
            'contact_title' => 'c.title',
            'product_code' => 'p.code',
            'product_name' => 'p.name',
            'quantity' => 'l.quantity',
            'unit_name' => 'u.name',
            'unit_price' => 'l.unit_price',
            'line_discount_amount' => 'l.line_discount_amount',
            'net_amount' => str_replace('::text', '', $this->expressions()['net_amount']),
            'vat_amount' => str_replace('::text', '', $this->expressions()['vat_amount']),
            'grand_total' => str_replace('::text', '', $this->expressions()['grand_total']),
            'currency' => 'd.currency',
        ];
    }

    /** @return array<string,string> */
    private function totals(Builder $base, ReportExecutionContext $context): array
    {
        if ($context->totalKeys === []) {
            return [];
        }

        $expressions = $this->expressions();
        $query = (clone $base)->selectRaw('d.currency AS currency')->groupBy('d.currency');
        $keys = [];

        foreach ($context->totalKeys as $key) {
            if (in_array($key, ['net_amount', 'vat_amount', 'grand_total'], true)) {
                $query->selectRaw('COALESCE(SUM('.str_replace('::text', '', $expressions[$key]).'), 0)::text AS '.$key);
                $keys[] = $key;
            }
        }

        return CurrencySeparatedTotals::fromGroups(
            $query->get(),
            $keys,
            $context->filters['currency'] ?? null,
        );
    }
}

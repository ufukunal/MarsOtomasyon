<?php

namespace App\Support\Reporting\Queries;

use App\Contracts\Reporting\ReportQuery;
use App\Support\Reporting\ReportColumnDefinition;
use App\Support\Reporting\ReportDefinition;
use App\Support\Reporting\ReportDrillDownDefinition;
use App\Support\Reporting\ReportExecutionContext;
use App\Support\Reporting\ReportFilterDefinition;
use App\Support\Reporting\ReportQueryResult;
use App\Support\Reporting\ReportSort;
use App\Support\Reporting\ReportTotalDefinition;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

abstract class AbstractFinanceMovementReportQuery implements ReportQuery
{
    abstract protected function key(): string;
    abstract protected function title(): string;
    abstract protected function permission(): string;
    abstract protected function accountPermission(): string;
    abstract protected function target(): string;
    abstract protected function movementTable(): string;
    abstract protected function accountTable(): string;
    abstract protected function accountForeignKey(): string;
    abstract protected function accountNameExpression(): string;

    protected function hasOrigin(): bool
    {
        return false;
    }

    public function definition(): ReportDefinition
    {
        $filters = [
            new ReportFilterDefinition('date_from', 'Başlangıç tarihi', 'date'),
            new ReportFilterDefinition('date_to', 'Bitiş tarihi', 'date'),
            new ReportFilterDefinition('account_id', 'Hesap', 'positive_integer'),
            new ReportFilterDefinition('direction', 'Yön'),
            new ReportFilterDefinition('movement_type', 'Hareket Türü'),
        ];

        if ($this->hasOrigin()) {
            $filters[] = new ReportFilterDefinition('origin', 'Kaynak');
        }

        $columns = [
            new ReportColumnDefinition('id', 'ID', 'integer', defaultVisible: false),
            new ReportColumnDefinition('account_id', 'Hesap ID', 'integer', defaultVisible: false),
            new ReportColumnDefinition('movement_date', 'Hareket Tarihi', 'date'),
            new ReportColumnDefinition('account_code', 'Hesap Kod'),
            new ReportColumnDefinition('account_name', 'Hesap'),
            new ReportColumnDefinition('direction', 'Yön'),
            new ReportColumnDefinition('movement_type', 'Hareket Türü'),
            new ReportColumnDefinition('amount', 'Tutar', 'decimal'),
            new ReportColumnDefinition('reference', 'Referans'),
            new ReportColumnDefinition('description', 'Açıklama', sortable: false),
        ];

        if ($this->hasOrigin()) {
            $columns[] = new ReportColumnDefinition('origin', 'Kaynak');
        }

        return new ReportDefinition(
            key: $this->key(),
            title: $this->title(),
            category: 'Finans',
            permission: $this->permission(),
            filters: $filters,
            columns: $columns,
            defaultSort: [
                new ReportSort('movement_date', 'desc'),
                new ReportSort('id', 'desc'),
            ],
            totals: [
                new ReportTotalDefinition('in', 'Giriş'),
                new ReportTotalDefinition('out', 'Çıkış'),
                new ReportTotalDefinition('net', 'Net'),
            ],
            drillDowns: [
                new ReportDrillDownDefinition(
                    key: 'account',
                    label: 'Hesap',
                    permission: $this->accountPermission(),
                    idColumn: 'account_id',
                    target: $this->target(),
                ),
            ],
        );
    }

    public function execute(ReportExecutionContext $context): ReportQueryResult
    {
        $base = DB::connection('period')
            ->table($this->movementTable().' as m')
            ->join($this->accountTable().' as a', 'a.id', '=', 'm.'.$this->accountForeignKey());

        $this->applyFilters($base, $context);
        $totalRows = (clone $base)->count('m.id');
        $rows = clone $base;

        if (! $context->hasColumn('id')) {
            $rows->selectRaw('m.id AS id');
        }
        if (! $context->hasColumn('account_id')) {
            $rows->selectRaw('a.id AS account_id');
        }

        foreach ($context->columns as $column) {
            $rows->selectRaw($this->selectExpression($column));
        }

        $sortMap = $this->sortMap();
        $hasId = false;
        foreach ($context->sort as $sort) {
            $rows->orderBy($sortMap[$sort->key], $sort->direction);
            $hasId = $hasId || $sort->key === 'id';
        }
        if (! $hasId) {
            $rows->orderBy('m.id');
        }

        return new ReportQueryResult(
            rows: $rows->offset($context->offset)->limit($context->limit)->get()
                ->map(fn (object $row): array => (array) $row)->all(),
            totals: $this->totals($base, $context),
            totalRows: $totalRows,
        );
    }

    private function applyFilters(Builder $query, ReportExecutionContext $context): void
    {
        $filters = $context->filters;
        if (isset($filters['date_from'])) {
            $query->whereDate('m.movement_date', '>=', $filters['date_from']);
        }
        if (isset($filters['date_to'])) {
            $query->whereDate('m.movement_date', '<=', $filters['date_to']);
        }
        if (isset($filters['account_id'])) {
            $query->where('a.id', $filters['account_id']);
        }
        foreach (['direction', 'movement_type', 'origin'] as $key) {
            if (isset($filters[$key])) {
                $query->where('m.'.$key, $filters[$key]);
            }
        }
    }

    private function selectExpression(string $column): string
    {
        $map = [
            'id' => 'm.id AS id',
            'account_id' => 'a.id AS account_id',
            'movement_date' => 'm.movement_date::text AS movement_date',
            'account_code' => 'a.code AS account_code',
            'account_name' => $this->accountNameExpression().' AS account_name',
            'direction' => 'm.direction AS direction',
            'movement_type' => 'm.movement_type AS movement_type',
            'amount' => 'm.amount::text AS amount',
            'reference' => 'm.reference AS reference',
            'description' => 'm.description AS description',
            'origin' => 'm.origin AS origin',
        ];

        return $map[$column];
    }

    /** @return array<string,string> */
    private function sortMap(): array
    {
        return [
            'id' => 'm.id',
            'account_id' => 'a.id',
            'movement_date' => 'm.movement_date',
            'account_code' => 'a.code',
            'account_name' => $this->accountNameExpression(),
            'direction' => 'm.direction',
            'movement_type' => 'm.movement_type',
            'amount' => 'm.amount',
            'reference' => 'm.reference',
            'origin' => 'm.origin',
        ];
    }

    /** @return array<string,mixed> */
    private function totals(Builder $base, ReportExecutionContext $context): array
    {
        if ($context->totalKeys === []) {
            return [];
        }

        $row = (clone $base)->selectRaw(
            "COALESCE(SUM(CASE WHEN m.direction = 'in' THEN m.amount ELSE 0 END), 0)::text AS in_total,
             COALESCE(SUM(CASE WHEN m.direction = 'out' THEN m.amount ELSE 0 END), 0)::text AS out_total,
             COALESCE(SUM(CASE WHEN m.direction = 'in' THEN m.amount ELSE -m.amount END), 0)::text AS net"
        )->first();

        $values = [
            'in' => $row->in_total ?? '0',
            'out' => $row->out_total ?? '0',
            'net' => $row->net ?? '0',
        ];

        return array_intersect_key($values, array_fill_keys($context->totalKeys, true));
    }
}

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

final class SecurityPortfolioReportQuery implements ReportQuery
{
    public function definition(): ReportDefinition
    {
        return new ReportDefinition(
            key: 'securities.portfolio',
            title: 'Çek / Senet Portföyü',
            category: 'Çek / Senet',
            permission: 'securities.view',
            filters: [
                new ReportFilterDefinition('date_from', 'Vade başlangıç', 'date'),
                new ReportFilterDefinition('date_to', 'Vade bitiş', 'date'),
                new ReportFilterDefinition('contact_id', 'Cari', 'positive_integer'),
                new ReportFilterDefinition('direction', 'Yön'),
                new ReportFilterDefinition('kind', 'Tür'),
                new ReportFilterDefinition('status', 'Durum'),
                new ReportFilterDefinition('currency', 'Döviz'),
            ],
            columns: [
                new ReportColumnDefinition('id', 'ID', 'integer', defaultVisible: false),
                new ReportColumnDefinition('instrument_no', 'Belge No'),
                new ReportColumnDefinition('direction', 'Yön'),
                new ReportColumnDefinition('kind', 'Tür'),
                new ReportColumnDefinition('contact_code', 'Cari Kod'),
                new ReportColumnDefinition('contact_title', 'Cari Ünvan'),
                new ReportColumnDefinition('bank_name', 'Banka'),
                new ReportColumnDefinition('issue_date', 'Düzenleme Tarihi', 'date'),
                new ReportColumnDefinition('due_date', 'Vade', 'date'),
                new ReportColumnDefinition('currency', 'Döviz'),
                new ReportColumnDefinition('amount', 'Tutar', 'decimal'),
                new ReportColumnDefinition('status', 'Durum'),
            ],
            defaultSort: [new ReportSort('due_date'), new ReportSort('id')],
            totals: [new ReportTotalDefinition('amount', 'Toplam Tutar')],
            drillDowns: [
                new ReportDrillDownDefinition('security', 'Çek / Senet', 'securities.view', 'id', 'securities'),
            ],
        );
    }

    public function execute(ReportExecutionContext $context): ReportQueryResult
    {
        $base = DB::connection('period')->table('securities as s')
            ->leftJoin('contacts as c', 'c.id', '=', 's.contact_id');
        $this->applyFilters($base, $context);
        $totalRows=(clone $base)->count('s.id');
        $rows=clone $base;
        if(!$context->hasColumn('id')) $rows->selectRaw('s.id AS id');
        foreach($context->columns as $column) $rows->selectRaw($this->selectExpression($column));
        $sortMap=$this->sortMap(); $hasId=false;
        foreach($context->sort as $sort){$rows->orderBy($sortMap[$sort->key],$sort->direction);$hasId=$hasId||$sort->key==='id';}
        if(!$hasId)$rows->orderBy('s.id');

        $totals=[];
        if($context->hasTotal('amount')){
            $totals['amount']=(string)((clone $base)->selectRaw('COALESCE(SUM(s.amount),0)::text AS value')->value('value')??'0');
        }

        return new ReportQueryResult(
            rows:$rows->offset($context->offset)->limit($context->limit)->get()->map(fn(object $r):array=>(array)$r)->all(),
            totals:$totals,
            totalRows:$totalRows,
        );
    }

    private function applyFilters(Builder $query, ReportExecutionContext $context): void
    {
        $f=$context->filters;
        if(isset($f['date_from']))$query->whereDate('s.due_date','>=',$f['date_from']);
        if(isset($f['date_to']))$query->whereDate('s.due_date','<=',$f['date_to']);
        foreach(['contact_id','direction','kind','status'] as $key){if(isset($f[$key]))$query->where('s.'.$key,$f[$key]);}
        if(isset($f['currency']))$query->where('s.currency',strtoupper((string)$f['currency']));
    }

    private function selectExpression(string $column): string
    {
        return [
            'id'=>'s.id AS id','instrument_no'=>'s.instrument_no AS instrument_no',
            'direction'=>'s.direction AS direction','kind'=>'s.kind AS kind',
            'contact_code'=>'c.code AS contact_code','contact_title'=>'c.title AS contact_title',
            'bank_name'=>'s.bank_name AS bank_name','issue_date'=>'s.issue_date::text AS issue_date',
            'due_date'=>'s.due_date::text AS due_date','currency'=>'s.currency AS currency',
            'amount'=>'s.amount::text AS amount','status'=>'s.status AS status',
        ][$column];
    }

    private function sortMap(): array
    {
        return [
            'id'=>'s.id','instrument_no'=>'s.instrument_no','direction'=>'s.direction','kind'=>'s.kind',
            'contact_code'=>'c.code','contact_title'=>'c.title','bank_name'=>'s.bank_name',
            'issue_date'=>'s.issue_date','due_date'=>'s.due_date','currency'=>'s.currency',
            'amount'=>'s.amount','status'=>'s.status',
        ];
    }
}

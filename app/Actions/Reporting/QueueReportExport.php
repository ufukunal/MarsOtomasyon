<?php

namespace App\Actions\Reporting;

use App\Jobs\GenerateReportExport;
use App\Models\ReportExportJob;
use App\Models\User;
use App\Support\Period\PeriodContext;
use App\Support\Reporting\ReportRegistry;
use App\Support\Reporting\ReportRequest;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class QueueReportExport
{
    public function __construct(
        private readonly RunReport $runReport,
        private readonly ReportRegistry $registry,
    ) {}

    public function handle(
        string $reportKey,
        ReportRequest $request,
        string $format,
        ?User $actor = null,
    ): ReportExportJob {
        PeriodContext::ensure();

        $actor ??= auth()->user();

        if (! $actor || ! $actor->is_active) {
            throw new AuthorizationException('Export kuyruğu için aktif kullanıcı gereklidir.');
        }

        $companyId = PeriodContext::companyId();
        $periodId = PeriodContext::periodId();

        if (! $companyId || ! $periodId) {
            throw new DomainException('Export kuyruğu şirket/dönem bağlamı gerektirir.');
        }

        $hasAccess = DB::connection('master')
            ->table('company_user')
            ->where('company_id', $companyId)
            ->where('user_id', $actor->id)
            ->exists()
            && DB::connection('master')
                ->table('period_user_access')
                ->where('period_id', $periodId)
                ->where('user_id', $actor->id)
                ->where('is_active', true)
                ->exists();

        if (! $hasAccess) {
            throw new AuthorizationException('Export kuyruğu için şirket/dönem erişiminiz yok.');
        }

        $format = strtolower(trim($format));
        $definition = $this->registry->query($reportKey)->definition();
        $gate = Gate::forUser($actor);
        $gate->authorize($definition->permission);

        if (! in_array($format, $definition->exporters, true) || $format === 'screen') {
            throw new DomainException("{$definition->title} raporu {$format} export desteklemiyor.");
        }

        $normalized = $this->runReport->handle(
            $reportKey,
            new ReportRequest(
                filters: $request->filters,
                columns: $request->columns,
                sort: $request->sort,
                limit: 1,
                offset: 0,
            ),
            $actor,
        );

        $maxExportRows = max(1, (int) config('reporting.max_export_rows', 50000));

        if ($normalized->totalRows > $maxExportRows) {
            throw new DomainException(
                "Rapor export satır sayısı {$maxExportRows} sınırını aşıyor.",
            );
        }

        $columns = array_map(fn ($column): string => $column->key, $normalized->columns);
        $sort = array_map(
            fn ($item): array => ['key' => $item->key, 'direction' => $item->direction],
            $normalized->sort,
        );
        $totalMap = $definition->totalMap();
        $requiresCost = collect($normalized->columns)->contains(
            fn ($column): bool => $column->costSensitive,
        );

        if (! $requiresCost) {
            foreach (array_keys($normalized->totals) as $totalKey) {
                if (($totalMap[$totalKey] ?? null)?->costSensitive) {
                    $requiresCost = true;
                    break;
                }
            }
        }

        $hashPayload = [
            'company_id' => $companyId,
            'period_id' => $periodId,
            'user_id' => $actor->id,
            'report_key' => $reportKey,
            'format' => $format,
            'filters' => $normalized->filters,
            'columns' => $columns,
            'sort' => $sort,
            'definition_version' => $normalized->definitionVersion,
        ];

        $job = ReportExportJob::query()->create([
            'company_id' => $companyId,
            'user_id' => $actor->id,
            'report_key' => $reportKey,
            'format' => $format,
            'filters' => $normalized->filters,
            'periods' => [$periodId],
            'permission_scope' => [
                'report_permission' => $definition->permission,
                'cost_view_required' => $requiresCost,
                'definition_version' => $normalized->definitionVersion,
            ],
            'parameters_hash' => hash(
                'sha256',
                json_encode($hashPayload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            ),
            'status' => 'queued',
            'progress' => 0,
        ]);

        dispatch(new GenerateReportExport(
            exportJobId: $job->id,
            companyId: $companyId,
            periodId: $periodId,
            userId: $actor->id,
            reportKey: $reportKey,
            format: $format,
            filters: $normalized->filters,
            columns: $columns,
            sort: $sort,
        ));

        return $job;
    }
}

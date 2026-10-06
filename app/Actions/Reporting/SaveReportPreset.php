<?php

namespace App\Actions\Reporting;

use App\Models\ReportFilterPreset;
use App\Models\User;
use App\Support\Period\PeriodContext;
use App\Support\Reporting\ReportRegistry;
use App\Support\Reporting\ReportRequest;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

final class SaveReportPreset
{
    public function __construct(
        private readonly RunReport $runReport,
        private readonly ReportRegistry $registry,
    ) {}

    /**
     * @param  array<string,mixed>  $filters
     * @param  list<string>  $columns
     * @param  list<array{key:string,direction?:string}>  $sort
     */
    public function handle(
        string $reportKey,
        string $name,
        array $filters,
        array $columns,
        array $sort,
        bool $shared,
        ?User $actor = null,
        ?int $presetId = null,
    ): ReportFilterPreset {
        PeriodContext::ensure();
        $actor ??= auth()->user();

        if (! $actor || ! $actor->is_active) {
            throw new AuthorizationException('Rapor preset kaydı için aktif kullanıcı gereklidir.');
        }

        $companyId = PeriodContext::companyId();

        if (! $companyId) {
            throw new DomainException('Rapor preset kaydı şirket bağlamı gerektirir.');
        }

        $name = trim($name);

        if ($name === '' || mb_strlen($name) > 120) {
            throw new DomainException('Preset adı 1-120 karakter olmalıdır.');
        }

        $definition = $this->registry->query($reportKey)->definition();
        Gate::forUser($actor)->authorize($definition->permission);

        if ($shared) {
            Gate::forUser($actor)->authorize('reports.presets.share');
        }

        $normalized = $this->runReport->handle(
            $reportKey,
            new ReportRequest(
                filters: $filters,
                columns: $columns,
                sort: $sort,
                limit: 1,
                offset: 0,
            ),
            $actor,
        );

        $payload = [
            'company_id' => $companyId,
            'user_id' => $shared ? null : $actor->id,
            'report_key' => $reportKey,
            'name' => $name,
            'filters' => $normalized->filters,
            'columns' => array_map(fn ($column): string => $column->key, $normalized->columns),
            'sort' => array_map(
                fn ($item): array => ['key' => $item->key, 'direction' => $item->direction],
                $normalized->sort,
            ),
            'is_shared' => $shared,
        ];

        if ($presetId === null) {
            return ReportFilterPreset::query()->create($payload);
        }

        $preset = ReportFilterPreset::query()
            ->whereKey($presetId)
            ->where('company_id', $companyId)
            ->firstOrFail();

        if (! $preset->is_shared && $preset->user_id !== $actor->id) {
            throw new AuthorizationException('Başka kullanıcının preset kaydı değiştirilemez.');
        }

        if ($preset->is_shared) {
            Gate::forUser($actor)->authorize('reports.presets.share');
        }

        $preset->fill($payload);
        $preset->save();

        return $preset->refresh();
    }
}

<?php

namespace App\Livewire\Reporting;

use App\Actions\Reporting\DeleteReportPreset;
use App\Actions\Reporting\ExportReport;
use App\Actions\Reporting\QueueReportExport;
use App\Actions\Reporting\RunReport;
use App\Actions\Reporting\SaveReportPreset;
use App\Support\Period\PeriodContext;
use App\Support\Reporting\ReportCatalog;
use App\Support\Reporting\ReportCatalogItem;
use App\Support\Reporting\ReportColumnDefinition;
use App\Support\Reporting\ReportDrillDownDefinition;
use App\Support\Reporting\ReportDrillDownResolver;
use App\Support\Reporting\ReportPresetRepository;
use App\Support\Reporting\ReportRequest;
use App\Support\Reporting\ReportResult;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ReportCenter extends Component
{
    #[Url(as: 'report')]
    public string $reportKey = '';

    /** @var array<string,mixed> */
    public array $filterValues = [];

    /** @var list<string> */
    public array $selectedColumns = [];

    public string $sortKey = '';

    public string $sortDirection = 'asc';

    public int $perPage = 50;

    public int $page = 1;

    public ?string $queueMessage = null;

    public string $presetName = '';

    public bool $presetShared = false;

    public ?int $selectedPresetId = null;

    public ?string $presetMessage = null;

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('reports.view'), 403);
        PeriodContext::ensure();

        if ($this->reportKey !== '') {
            $this->initializeReport($this->reportKey);
            $this->applyIncomingFilters();
        }
    }

    public function selectReport(string $key): void
    {
        $key = trim($key);
        $this->queueMessage = null;

        if ($key === '') {
            $this->resetReport();

            return;
        }

        $this->initializeReport($key);
    }

    public function apply(): void
    {
        abort_unless($this->reportKey !== '', 422);
        $this->page = 1;
        $this->queueMessage = null;
    }

    public function clearFilters(): void
    {
        $this->filterValues = [];
        $this->page = 1;
        $this->queueMessage = null;
    }

    public function setPerPage(int $perPage): void
    {
        abort_unless(in_array($perPage, [25, 50, 100], true), 422);
        $this->perPage = $perPage;
        $this->page = 1;
    }

    public function goToPage(int $page): void
    {
        abort_unless($page >= 1, 422);
        $this->page = $page;
    }

    public function export(string $format): StreamedResponse
    {
        $report = $this->authorizedReportForExport($format);

        $artifact = app(ExportReport::class)->handle(
            $report->key,
            $this->exportRequest(),
            $format,
        );

        return response()->streamDownload(
            static function () use ($artifact): void {
                echo $artifact->contents;
            },
            $artifact->filename,
            ['Content-Type' => $artifact->mimeType],
        );
    }

    public function queueExport(string $format): void
    {
        $report = $this->authorizedReportForExport($format);

        $job = app(QueueReportExport::class)->handle(
            $report->key,
            $this->exportRequest(),
            $format,
        );

        $this->queueMessage = "Export #{$job->id} kuyruğa alındı.";
    }

    public function savePreset(): void
    {
        abort_unless($this->reportKey !== '', 422);

        $preset = app(SaveReportPreset::class)->handle(
            reportKey: $this->reportKey,
            name: $this->presetName,
            filters: $this->submittedFilters(),
            columns: $this->selectedColumns,
            sort: $this->sortKey !== ''
                ? [['key' => $this->sortKey, 'direction' => $this->sortDirection]]
                : [],
            shared: $this->presetShared,
            presetId: $this->selectedPresetId,
        );

        $this->selectedPresetId = $preset->id;
        $this->presetName = $preset->name;
        $this->presetMessage = 'Preset kaydedildi.';
    }

    public function applyPreset(int $presetId): void
    {
        $actor = auth()->user();
        abort_unless($actor !== null && $this->reportKey !== '', 403);

        $preset = app(ReportPresetRepository::class)
            ->forReport($this->reportKey, $actor)
            ->firstWhere('id', $presetId);

        abort_unless($preset !== null, 404);

        $this->selectedPresetId = $preset->id;
        $this->presetName = $preset->name;
        $this->presetShared = (bool) $preset->is_shared;
        $this->filterValues = $preset->filters ?? [];
        $this->selectedColumns = $preset->columns ?? [];
        $firstSort = ($preset->sort ?? [])[0] ?? null;
        $this->sortKey = $firstSort !== null ? $firstSort['key'] : '';
        $this->sortDirection = $firstSort !== null ? $firstSort['direction'] : 'asc';
        $this->page = 1;
        $this->presetMessage = 'Preset uygulandı.';
    }

    public function deletePreset(): void
    {
        abort_unless($this->selectedPresetId !== null, 422);

        app(DeleteReportPreset::class)->handle($this->selectedPresetId);

        $this->selectedPresetId = null;
        $this->presetName = '';
        $this->presetShared = false;
        $this->presetMessage = 'Preset silindi.';
    }

    /**
     * @param  array<string,mixed>  $row
     * @param  list<ReportDrillDownDefinition>  $definitions
     * @return array{label:string,url:string}|null
     */
    public function drillDownLink(array $row, array $definitions): ?array
    {
        $actor = auth()->user();

        if (! $actor) {
            return null;
        }

        $resolver = app(ReportDrillDownResolver::class);

        foreach ($definitions as $definition) {
            $resolved = $resolver->resolve($definition, $row, $actor);

            if ($resolved !== null) {
                return $resolved;
            }
        }

        return null;
    }

    /** @param array<string,mixed> $row */
    public function formatCell(array $row, ReportColumnDefinition $column): string
    {
        $value = $row[$column->key] ?? null;

        if ($value === null || $value === '') {
            return '';
        }

        if ($column->type === 'date' && is_string($value)) {
            try {
                return CarbonImmutable::parse($value)->format('d.m.Y');
            } catch (\Throwable) {
                return $value;
            }
        }

        if ($column->type === 'datetime' && is_string($value)) {
            try {
                return CarbonImmutable::parse($value)->format('d.m.Y H:i:s');
            } catch (\Throwable) {
                return $value;
            }
        }

        if ($column->type === 'boolean') {
            return filter_var($value, FILTER_VALIDATE_BOOL) ? 'Evet' : 'Hayır';
        }

        return is_scalar($value) ? (string) $value : '';
    }

    public function isNumericColumn(ReportColumnDefinition $column): bool
    {
        return in_array($column->type, ['integer', 'decimal', 'money', 'quantity'], true);
    }

    public function render(): View
    {
        $catalog = app(ReportCatalog::class);
        $runReport = app(RunReport::class);
        $catalogItems = $catalog->forActor();
        $selectedReport = $this->findReport($catalogItems, $this->reportKey);
        $presets = $selectedReport && auth()->user()
            ? app(ReportPresetRepository::class)->forReport($selectedReport->key, auth()->user())
            : collect();
        $result = null;
        $reportError = null;

        if ($this->reportKey !== '' && ! $selectedReport) {
            abort(403);
        }

        if ($selectedReport) {
            try {
                $result = $runReport->handle(
                    $selectedReport->key,
                    new ReportRequest(
                        filters: $this->submittedFilters(),
                        columns: $this->selectedColumns,
                        sort: $this->sortKey !== ''
                            ? [[
                                'key' => $this->sortKey,
                                'direction' => $this->sortDirection,
                            ]]
                            : [],
                        limit: $this->perPage,
                        offset: ($this->page - 1) * $this->perPage,
                    ),
                );
            } catch (DomainException $exception) {
                $reportError = $exception->getMessage();
            }
        }

        $totalPages = $result instanceof ReportResult
            ? max(1, (int) ceil($result->totalRows / $this->perPage))
            : 1;

        return view('livewire.reporting.report-center', [
            'catalogItems' => $catalogItems,
            'catalogGroups' => collect($catalogItems)->groupBy('category'),
            'selectedReport' => $selectedReport,
            'presets' => $presets,
            'result' => $result,
            'reportError' => $reportError,
            'totalPages' => $totalPages,
        ])->layout('layouts.app', [
            'pageTitle' => 'Rapor Merkezi',
            'pageDescription' => 'Operasyonel raporlar kanonik dönem verilerinden üretilir.',
        ]);
    }

    /** @param list<ReportCatalogItem> $catalogItems */
    private function findReport(array $catalogItems, string $key): ?ReportCatalogItem
    {
        if ($key === '') {
            return null;
        }

        foreach ($catalogItems as $item) {
            if ($item->key === $key) {
                return $item;
            }
        }

        return null;
    }

    private function initializeReport(string $key): void
    {
        $catalogItems = app(ReportCatalog::class)->forActor();
        $report = $this->findReport($catalogItems, $key);
        abort_unless($report !== null, 403);

        $this->reportKey = $report->key;
        $this->filterValues = [];
        $this->selectedColumns = array_values(array_map(
            fn (ReportColumnDefinition $column): string => $column->key,
            array_filter(
                $report->columns,
                fn (ReportColumnDefinition $column): bool => $column->defaultVisible,
            ),
        ));
        $this->sortKey = $report->defaultSort[0]->key ?? '';
        $this->sortDirection = $report->defaultSort[0]->direction ?? 'asc';
        $this->page = 1;
        $this->queueMessage = null;
        $this->selectedPresetId = null;
        $this->presetName = '';
        $this->presetShared = false;
        $this->presetMessage = null;
    }

    private function applyIncomingFilters(): void
    {
        $incoming = request()->query('filters', []);

        if (! is_array($incoming)) {
            return;
        }

        $report = $this->findReport(
            app(ReportCatalog::class)->forActor(),
            $this->reportKey,
        );

        if (! $report) {
            return;
        }

        $allowed = array_fill_keys(
            array_map(fn ($filter): string => $filter->key, $report->filters),
            true,
        );

        foreach ($incoming as $key => $value) {
            if (isset($allowed[$key]) && (is_scalar($value) || $value === null)) {
                $this->filterValues[$key] = $value;
            }
        }
    }

    private function resetReport(): void
    {
        $this->reportKey = '';
        $this->filterValues = [];
        $this->selectedColumns = [];
        $this->sortKey = '';
        $this->sortDirection = 'asc';
        $this->page = 1;
        $this->queueMessage = null;
        $this->selectedPresetId = null;
        $this->presetName = '';
        $this->presetShared = false;
        $this->presetMessage = null;
    }

    private function authorizedReportForExport(string $format): ReportCatalogItem
    {
        abort_unless($this->reportKey !== '', 422);

        $report = $this->findReport(
            app(ReportCatalog::class)->forActor(),
            $this->reportKey,
        );

        abort_unless($report !== null, 403);
        abort_unless(in_array($format, $report->exporters, true) && $format !== 'screen', 422);

        return $report;
    }

    private function exportRequest(): ReportRequest
    {
        return new ReportRequest(
            filters: $this->submittedFilters(),
            columns: $this->selectedColumns,
            sort: $this->sortKey !== ''
                ? [[
                    'key' => $this->sortKey,
                    'direction' => $this->sortDirection,
                ]]
                : [],
            limit: $this->perPage,
            offset: 0,
        );
    }

    /** @return array<string,mixed> */
    private function submittedFilters(): array
    {
        return array_filter(
            $this->filterValues,
            fn (mixed $value): bool => $value !== null && $value !== '',
        );
    }
}

<?php

namespace App\Livewire\Reporting;

use App\Actions\Reporting\RunReport;
use App\Support\Period\PeriodContext;
use App\Support\Reporting\ReportCatalog;
use App\Support\Reporting\ReportCatalogItem;
use App\Support\Reporting\ReportColumnDefinition;
use App\Support\Reporting\ReportRequest;
use App\Support\Reporting\ReportResult;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

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
    }

    public function clearFilters(): void
    {
        $this->filterValues = [];
        $this->page = 1;
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

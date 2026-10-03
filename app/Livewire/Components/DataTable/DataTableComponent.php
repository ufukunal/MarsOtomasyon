<?php

namespace App\Livewire\Components\DataTable;

use App\Support\Formatting\TableValueFormatter;
use App\Support\Search\SearchNormalizer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

abstract class DataTableComponent extends Component
{
    use WithPagination;

    /** @var class-string<Model> */
    public string $model;

    #[Url]
    public string $search = '';

    #[Url]
    public string $sort = 'id';

    #[Url]
    public string $direction = 'asc';

    public int $perPage = 25;
    public array $filterValues = [];
    public array $selected = [];
    public array $hiddenColumns = [];

    abstract public function columns(): array;

    public function boot(): void
    {
        if ($this->hiddenColumns === [] && app()->bound('session')) {
            $stored = session($this->columnSessionKey(), []);
            $this->hiddenColumns = is_array($stored) ? $stored : [];
        }
    }

    public function filters(): array
    {
        return [];
    }

    public function rowActions(): array
    {
        return [];
    }

    public function bulkActions(): array
    {
        return [];
    }

    public function emptyAction(): ?array
    {
        return null;
    }

    protected function baseQuery(): Builder
    {
        return ($this->model)::query();
    }

    protected function query(): Builder
    {
        $query = $this->baseQuery();
        $columns = collect($this->columns());
        $normalized = SearchNormalizer::make($this->search);

        if ($normalized !== '') {
            $searchable = $columns->filter(fn (Column $column): bool => $column->searchable);

            if ($searchable->isNotEmpty()) {
                if (method_exists($query->getModel(), 'scopeSearch')) {
                    $query->search($this->search);
                } else {
                    $query->where(function (Builder $builder) use ($searchable): void {
                        foreach ($searchable as $column) {
                            $builder->orWhere($column->key, 'ilike', '%'.$this->search.'%');
                        }
                    });
                }
            }
        }

        foreach ($this->filters() as $filter) {
            $value = $this->filterValues[$filter->key] ?? null;

            if ($value === null || $value === '') {
                continue;
            }

            if ($filter instanceof SelectFilter) {
                $query->where($filter->key, $value);
            }

            if ($filter instanceof DateRangeFilter && is_array($value)) {
                if (! empty($value['from'])) {
                    $query->whereDate($filter->key, '>=', $value['from']);
                }

                if (! empty($value['to'])) {
                    $query->whereDate($filter->key, '<=', $value['to']);
                }
            }
        }

        $sortColumn = $columns->first(
            fn (Column $column): bool => $column->key === $this->sort && $column->sortable
        ) ?? $columns->first(fn (Column $column): bool => $column->sortable);

        if ($sortColumn) {
            $query->orderBy($sortColumn->key, $this->direction === 'desc' ? 'desc' : 'asc');
        }

        return $query;
    }

    public function rows(): LengthAwarePaginator
    {
        return $this->query()->paginate($this->perPage);
    }

    public function formattedValue(Model $row, Column $column): string
    {
        return TableValueFormatter::format($column, data_get($row, $column->key));
    }

    public function sortBy(string $column): void
    {
        $allowed = collect($this->columns())
            ->contains(fn (Column $item): bool => $item->key === $column && $item->sortable);

        abort_unless($allowed, 422);

        if ($this->sort === $column) {
            $this->direction = $this->direction === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sort = $column;
            $this->direction = 'asc';
        }

        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterValues(): void
    {
        $this->resetPage();
    }

    public function setPerPage(int $perPage): void
    {
        abort_unless(in_array($perPage, [25, 50, 100], true), 422);
        $this->perPage = $perPage;
        $this->resetPage();
    }

    public function toggleColumn(string $column): void
    {
        abort_unless(
            collect($this->columns())->contains(fn (Column $item): bool => $item->key === $column),
            422,
        );

        $this->hiddenColumns[$column] = ! ($this->hiddenColumns[$column] ?? false);
        session([$this->columnSessionKey() => $this->hiddenColumns]);
    }

    public function selectVisible(): void
    {
        $this->selected = collect($this->rows()->items())
            ->map(fn (Model $model) => $model->getKey())
            ->values()
            ->all();
    }

    public function clearSelection(): void
    {
        $this->selected = [];
    }

    public function runRowAction(string $method, int|string $id): mixed
    {
        $allowed = collect($this->rowActions())->contains(
            fn (array $action): bool => ($action['method'] ?? null) === $method
        );

        abort_unless($allowed && method_exists($this, $method), 422);

        return $this->{$method}($id);
    }

    public function runBulkAction(string $method): mixed
    {
        $allowed = collect($this->bulkActions())->contains(
            fn (array $action): bool => ($action['method'] ?? null) === $method
        );

        abort_unless($allowed && method_exists($this, $method), 422);

        return $this->{$method}();
    }

    public function exportCsv(): StreamedResponse
    {
        $columns = $this->exportColumns();
        $filename = class_basename($this->model).'-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($columns): void {
            $handle = fopen('php://output', 'wb');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $columns->pluck('label')->all(), ';');

            $this->query()->chunkById(500, function ($rows) use ($columns, $handle): void {
                foreach ($rows as $row) {
                    fputcsv($handle, $columns->map(
                        fn (Column $column): string => $this->formattedValue($row, $column)
                    )->all(), ';');
                }
            });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function exportXlsx(): StreamedResponse
    {
        $columns = $this->exportColumns();
        $filename = class_basename($this->model).'-'.now()->format('Ymd-His').'.xlsx';

        return response()->streamDownload(function () use ($columns): void {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->fromArray($columns->pluck('label')->all(), null, 'A1');
            $line = 2;

            $this->query()->chunkById(500, function ($rows) use ($columns, $sheet, &$line): void {
                foreach ($rows as $row) {
                    $sheet->fromArray($columns->map(
                        fn (Column $column): string => $this->formattedValue($row, $column)
                    )->all(), null, 'A'.$line);
                    $line++;
                }
            });

            (new Xlsx($spreadsheet))->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function render(): View
    {
        return view('livewire.components.data-table', [
            'rows' => $this->rows(),
            'columns' => $this->columns(),
            'filters' => $this->filters(),
            'rowActions' => $this->rowActions(),
            'bulkActions' => $this->bulkActions(),
            'emptyAction' => $this->emptyAction(),
        ]);
    }

    private function exportColumns()
    {
        return collect($this->columns())
            ->reject(fn (Column $column): bool => $this->hiddenColumns[$column->key] ?? false)
            ->values();
    }

    private function columnSessionKey(): string
    {
        return 'datatable.hidden.'.str_replace('\\', '.', static::class);
    }
}

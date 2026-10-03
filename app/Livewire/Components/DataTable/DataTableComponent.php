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

    /** @var array<string, mixed> */
    public array $filterValues = [];

    /** @var array<int, int|string> */
    public array $selected = [];

    /** @var array<string, bool> */
    public array $hiddenColumns = [];

    abstract public function columns(): array;

    public function filters(): array
    {
        return [];
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
        );

        if (! $sortColumn) {
            $sortColumn = $columns->first(fn (Column $column): bool => $column->sortable);
        }

        if ($sortColumn) {
            $query->orderBy(
                $sortColumn->key,
                $this->direction === 'desc' ? 'desc' : 'asc',
            );
        }

        return $query;
    }

    public function rows(): LengthAwarePaginator
    {
        return $this->query()->paginate($this->perPage);
    }

    public function formattedValue(Model $row, Column $column): string
    {
        return TableValueFormatter::format(
            $column,
            data_get($row, $column->key),
        );
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
        $exists = collect($this->columns())->contains(
            fn (Column $item): bool => $item->key === $column
        );

        abort_unless($exists, 422);

        $this->hiddenColumns[$column] = ! ($this->hiddenColumns[$column] ?? false);
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

    public function exportCsv(): StreamedResponse
    {
        $filename = class_basename($this->model).'-'.now()->format('Ymd-His').'.csv';
        $columns = collect($this->columns())
            ->reject(fn (Column $column): bool => $this->hiddenColumns[$column->key] ?? false)
            ->values();

        return response()->streamDownload(function () use ($columns): void {
            $handle = fopen('php://output', 'wb');

            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $columns->pluck('label')->all(), ';');

            $this->query()->chunkById(500, function ($rows) use ($columns, $handle): void {
                foreach ($rows as $row) {
                    fputcsv(
                        $handle,
                        $columns
                            ->map(fn (Column $column): string => $this->formattedValue($row, $column))
                            ->all(),
                        ';',
                    );
                }
            });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function render(): View
    {
        return view('livewire.components.data-table', [
            'rows' => $this->rows(),
            'columns' => $this->columns(),
            'filters' => $this->filters(),
        ]);
    }
}

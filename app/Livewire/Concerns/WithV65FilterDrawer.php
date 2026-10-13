<?php

namespace App\Livewire\Concerns;

use App\Livewire\Components\DataTable\DateRangeFilter;
use App\Livewire\Components\DataTable\SelectFilter;
use DateTimeImmutable;

trait WithV65FilterDrawer
{
    /** @var array{search:string,filters:array<string,mixed>,sort:string,direction:string} */
    public array $v65DraftFilters = [];

    public string $v65FilterTab = 'filters';

    /** @return array{search:string,filters:array<string,mixed>,sort:string,direction:string} */
    protected function v65ReadFilterState(): array
    {
        return [
            'search' => $this->search,
            'filters' => $this->filterValues,
            'sort' => $this->sort,
            'direction' => $this->direction,
        ];
    }

    /** @param array{search:string,filters:array<string,mixed>,sort:string,direction:string} $state */
    protected function v65WriteFilterState(array $state): void
    {
        $this->search = $state['search'];
        $this->filterValues = $state['filters'];
        $this->sort = $state['sort'];
        $this->direction = $state['direction'];
    }

    /** @return array<string,array{label:string,type:string,options?:array<int|string,string>}> */
    protected function v65FilterDefinitions(): array
    {
        $definitions = [];
        foreach ($this->filters() as $filter) {
            if ($filter instanceof SelectFilter) {
                $definitions[$filter->key] = ['label' => $filter->label, 'type' => 'select', 'options' => $filter->options];
            } elseif ($filter instanceof DateRangeFilter) {
                $definitions[$filter->key] = ['label' => $filter->label, 'type' => 'date-range'];
            }
        }

        return $definitions;
    }

    /** @return array<string,string> */
    protected function v65SortOptions(): array
    {
        return collect($this->columns())
            ->filter(fn ($column): bool => $column->sortable)
            ->mapWithKeys(fn ($column): array => [$column->key => $column->label])
            ->all();
    }

    protected function v65DefaultSort(): string
    {
        return 'id';
    }

    protected function v65DefaultDirection(): string
    {
        return 'asc';
    }

    public function openV65Filters(): void
    {
        $this->v65DraftFilters = $this->v65ReadFilterState();
        $this->v65FilterTab = 'filters';
    }

    public function closeV65Filters(): void
    {
        // Discard edits that were not applied.
        $this->v65DraftFilters = [];
        $this->v65FilterTab = 'filters';
    }

    public function v65SetFilterTab(string $tab): void
    {
        abort_unless(in_array($tab, ['filters', 'sorting'], true), 422);
        $this->v65FilterTab = $tab;
    }

    public function applyV65Filters(): void
    {
        $draft = $this->v65DraftFilters !== [] ? $this->v65DraftFilters : $this->v65ReadFilterState();
        $search = trim((string) ($draft['search'] ?? ''));
        abort_unless(mb_strlen($search) <= 255, 422);

        $provided = $draft['filters'] ?? [];
        abort_unless(is_array($provided), 422);
        $definitions = $this->v65FilterDefinitions();
        $filters = [];

        foreach ($provided as $key => $value) {
            abort_unless(is_string($key) && array_key_exists($key, $definitions), 422);
            $definition = $definitions[$key];

            if ($definition['type'] === 'select') {
                abort_unless(is_scalar($value) || $value === null, 422);
                if ($value !== null && (string) $value !== '') {
                    $options = $definition['options'] ?? [];
                    abort_unless(array_key_exists((string) $value, $options), 422);
                    $filters[$key] = (string) $value;
                }
            } elseif ($definition['type'] === 'date-range') {
                abort_unless(is_array($value), 422);
                $range = [];
                foreach (['from', 'to'] as $end) {
                    $date = $value[$end] ?? '';
                    abort_unless(is_string($date), 422);
                    if ($date !== '') {
                        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
                        abort_unless($parsed && $parsed->format('Y-m-d') === $date, 422);
                        $range[$end] = $date;
                    }
                }
                abort_unless(!isset($range['from'], $range['to']) || $range['from'] <= $range['to'], 422);
                if ($range !== []) {
                    $filters[$key] = $range;
                }
            }
        }

        $sort = (string) ($draft['sort'] ?? $this->v65DefaultSort());
        $direction = (string) ($draft['direction'] ?? $this->v65DefaultDirection());
        abort_unless($sort === $this->v65DefaultSort() || array_key_exists($sort, $this->v65SortOptions()), 422);
        abort_unless(in_array($direction, ['asc', 'desc'], true), 422);

        $this->v65WriteFilterState(compact('search', 'filters', 'sort', 'direction'));
        $this->resetPage();
        $this->closeV65Filters();
    }

    public function clearV65Filters(): void
    {
        $this->v65WriteFilterState([
            'search' => '',
            'filters' => [],
            'sort' => $this->v65DefaultSort(),
            'direction' => $this->v65DefaultDirection(),
        ]);
        $this->resetPage();
        $this->closeV65Filters();
    }

    public function removeV65Filter(string $key): void
    {
        $state = $this->v65ReadFilterState();
        if ($key === 'search') {
            $state['search'] = '';
        } elseif ($key === 'sort') {
            $state['sort'] = $this->v65DefaultSort();
            $state['direction'] = $this->v65DefaultDirection();
        } elseif (str_starts_with($key, 'filter:')) {
            $field = substr($key, 7);
            abort_unless(array_key_exists($field, $this->v65FilterDefinitions()), 422);
            unset($state['filters'][$field]);
        } else {
            abort(422);
        }

        $this->v65WriteFilterState($state);
        $this->resetPage();
    }

    /** @return list<array{key:string,label:string}> */
    public function v65ActiveFilterLabels(): array
    {
        $state = $this->v65ReadFilterState();
        $chips = [];
        if (trim($state['search']) !== '') {
            $chips[] = ['key' => 'search', 'label' => 'Arama: '.$state['search']];
        }

        foreach ($this->v65FilterDefinitions() as $key => $definition) {
            $value = $state['filters'][$key] ?? null;
            if ($value === null || $value === '') {
                continue;
            }

            if ($definition['type'] === 'select') {
                $caption = $definition['options'][(string) $value] ?? (string) $value;
                $chips[] = ['key' => 'filter:'.$key, 'label' => $definition['label'].': '.$caption];
            } elseif (is_array($value)) {
                $from = $value['from'] ?? '';
                $to = $value['to'] ?? '';
                if ($from !== '' || $to !== '') {
                    $chips[] = ['key' => 'filter:'.$key, 'label' => $definition['label'].': '.($from ?: '…').' – '.($to ?: '…')];
                }
            }
        }

        if ($state['sort'] !== $this->v65DefaultSort() || $state['direction'] !== $this->v65DefaultDirection()) {
            $chips[] = [
                'key' => 'sort',
                'label' => 'Sıra: '.($this->v65SortOptions()[$state['sort']] ?? 'Varsayılan').' · '.($state['direction'] === 'desc' ? 'Azalan' : 'Artan'),
            ];
        }

        return $chips;
    }

    public function activeV65FilterCount(): int
    {
        return count($this->v65ActiveFilterLabels());
    }

    /** @return array<string,string> */
    public function v65AvailableSortOptions(): array
    {
        return $this->v65SortOptions();
    }
}

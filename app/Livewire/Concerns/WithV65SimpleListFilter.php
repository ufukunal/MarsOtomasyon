<?php

namespace App\Livewire\Concerns;

/**
 * Filter drawer adapter for standalone list screens with a listSearch property.
 * Selection/edit forms on the same screen remain untouched.
 */
trait WithV65SimpleListFilter
{
    use WithV65FilterDrawer;

    public string $v65SimpleSort = '';

    public string $v65SimpleDirection = 'desc';

    /** @return array{search:string,filters:array<string,mixed>,sort:string,direction:string} */
    protected function v65ReadFilterState(): array
    {
        return [
            'search' => $this->listSearch,
            'filters' => [],
            'sort' => $this->v65SimpleSort,
            'direction' => $this->v65SimpleDirection,
        ];
    }

    /** @param array{search:string,filters:array<string,mixed>,sort:string,direction:string} $state */
    protected function v65WriteFilterState(array $state): void
    {
        $this->listSearch = $state['search'];
        $this->v65SimpleSort = $state['sort'];
        $this->v65SimpleDirection = $state['direction'];
    }

    /** @return array<string,array{label:string,type:string,options?:array<int|string,string>}> */
    protected function v65FilterDefinitions(): array
    {
        return [];
    }

    /** @return array<string,string> */
    protected function v65SortOptions(): array
    {
        return ['id' => 'Kayıt Numarası'];
    }

    protected function v65DefaultSort(): string
    {
        return '';
    }

    protected function v65DefaultDirection(): string
    {
        return 'desc';
    }
}

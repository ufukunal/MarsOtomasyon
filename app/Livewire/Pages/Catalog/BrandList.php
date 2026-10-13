<?php

namespace App\Livewire\Pages\Catalog;

use App\Livewire\Components\DataTable\Column;
use App\Livewire\Components\DataTable\DataTableComponent;
use App\Models\Period\Brand;
use Illuminate\Database\Eloquent\Model;

/** @extends DataTableComponent<Brand> */
class BrandList extends DataTableComponent
{
    public string $model = Brand::class;

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('brands.view'), 403);
    }

    /** @return list<Column> */
    public function columns(): array
    {
        return [
            Column::make('name', 'Marka')->searchable()->sortable(),
            Column::make('is_active', 'Durum')->sortable(),
        ];
    }

    /** @return array<string,mixed>|null */
    public function emptyAction(): ?array
    {
        return auth()->user()?->can('brands.create')
            ? ['label' => 'Yeni Marka', 'route' => 'brands.create']
            : null;
    }

    /** @return list<array<string,mixed>> */
    public function rowActions(): array
    {
        // Records open via their linked identifiers, not redundant row buttons.
        return [];
    }

    public function columnUrl(Model $row, Column $column): ?string
    {
        return $column->key === 'name' && auth()->user()?->can('brands.update')
            ? route('brands.edit', ['brand' => $row->getKey()])
            : null;
    }

    public function editBrand(int|string $id): mixed
    {
        abort_unless(auth()->user()?->can('brands.update'), 403);

        return $this->redirectRoute('brands.edit', ['brand' => $id], navigate: false);
    }
}

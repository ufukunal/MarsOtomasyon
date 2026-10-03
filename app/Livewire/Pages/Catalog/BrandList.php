<?php

namespace App\Livewire\Pages\Catalog;

use App\Livewire\Components\DataTable\Column;
use App\Livewire\Components\DataTable\DataTableComponent;
use App\Models\Period\Brand;

class BrandList extends DataTableComponent
{
    public string $model = Brand::class;

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('brands.view'), 403);
    }

    public function columns(): array
    {
        return [
            Column::make('name', 'Marka')->sortable(),
            Column::make('is_active', 'Durum')->sortable(),
        ];
    }
}

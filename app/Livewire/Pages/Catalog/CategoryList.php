<?php

namespace App\Livewire\Pages\Catalog;

use App\Livewire\Components\DataTable\Column;
use App\Livewire\Components\DataTable\DataTableComponent;
use App\Models\Period\ProductCategory;

class CategoryList extends DataTableComponent
{
    public string $model = ProductCategory::class;

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('product_categories.view'), 403);
    }

    public function columns(): array
    {
        return [
            Column::make('name', 'Kategori')->sortable(),
            Column::make('sort_order', 'Sıra')->sortable(),
            Column::make('is_active', 'Durum')->sortable(),
        ];
    }
}

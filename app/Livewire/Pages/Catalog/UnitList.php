<?php

namespace App\Livewire\Pages\Catalog;

use App\Livewire\Components\DataTable\Column;
use App\Livewire\Components\DataTable\DataTableComponent;
use App\Models\Period\Unit;

class UnitList extends DataTableComponent
{
    public string $model = Unit::class;

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('units.view'), 403);
    }

    public function columns(): array
    {
        return [
            Column::make('code', 'Kod')->sortable(),
            Column::make('name', 'Ad')->sortable(),
            Column::make('is_base', 'Temel')->sortable(),
            Column::make('is_active', 'Durum')->sortable(),
        ];
    }
}

<?php

namespace App\Livewire\Pages\Pricing;

use App\Livewire\Components\DataTable\Column;
use App\Livewire\Components\DataTable\DataTableComponent;
use App\Models\Period\PriceList;

class PriceListList extends DataTableComponent
{
    public string $model = PriceList::class;

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('price_lists.view'), 403);
    }

    public function columns(): array
    {
        return [
            Column::make('name', 'Ad')->sortable(),
            Column::make('currency', 'Para Birimi')->sortable(),
            Column::make('vat_included', 'Giriş KDV Dahil')->sortable(),
            Column::make('is_default', 'Varsayılan')->sortable(),
            Column::make('is_active', 'Durum')->sortable(),
        ];
    }
}

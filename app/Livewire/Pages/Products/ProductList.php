<?php

namespace App\Livewire\Pages\Products;

use App\Livewire\Components\DataTable\Column;
use App\Livewire\Components\DataTable\DataTableComponent;
use App\Livewire\Components\DataTable\SelectFilter;
use App\Models\Period\Product;

class ProductList extends DataTableComponent
{
    public string $model = Product::class;

    public function mount(): void
    {
        $this->authorize('viewAny', Product::class);
    }

    public function columns(): array
    {
        $columns = [
            Column::make('code', 'Kod')->searchable()->sortable(),
            Column::make('name', 'Ad')->searchable()->sortable(),
            Column::make('barcode', 'Barkod')->searchable(),
            Column::make('list_price', 'Liste Fiyatı')->money()->sortable(),
            Column::make('vat_rate', 'KDV %')->alignEnd(),
            Column::make('kind', 'Tip')->sortable(),
            Column::make('is_active', 'Durum')->sortable(),
        ];

        // Faz 2 product_costs gelene kadar maliyet kolonu hiç tanımlanmaz.
        // cost.view yetkisi olsa bile var olmayan derived kaynağı göstermeyiz.
        return $columns;
    }

    public function filters(): array
    {
        return [
            SelectFilter::make('kind', 'Tip')->options([
                'normal' => 'Normal',
                'set' => 'Set',
                'configurable' => 'Konfigüre',
            ]),
            SelectFilter::make('is_active', 'Durum')->options([1 => 'Aktif', 0 => 'Pasif']),
        ];
    }
}

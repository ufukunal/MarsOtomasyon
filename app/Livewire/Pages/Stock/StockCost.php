<?php

namespace App\Livewire\Pages\Stock;

use App\Livewire\Components\DataTable\Column;

/** v65 inventory-cost view with strict stock and cost permission guards. */
class StockCost extends StockStatus
{
    public function mount(): void
    {
        parent::mount();
        abort_unless(auth()->user()?->can('cost.view'), 403);
    }

    /** @return list<Column> */
    public function columns(): array
    {
        return [
            Column::make('product_code', 'Ürün Kodu')->searchable()->sortable(),
            Column::make('product_name', 'Ürün Adı')->searchable()->sortable(),
            Column::make('location_name', 'Depo / Lokasyon')->searchable()->sortable(),
            Column::make('quantity', 'Miktar')->quantity()->sortable(),
            Column::make('moving_average', 'Hareketli Ortalama')->money()->sortable(),
            Column::make('stock_value', 'Stok Değeri')->money()->sortable(),
            Column::make('stock_status', 'Durum')->sortable(),
        ];
    }
}

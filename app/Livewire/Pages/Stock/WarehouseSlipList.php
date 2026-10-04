<?php

namespace App\Livewire\Pages\Stock;

use App\Livewire\Components\DataTable\Column;
use App\Livewire\Components\DataTable\DataTableComponent;
use App\Livewire\Components\DataTable\DateRangeFilter;
use App\Livewire\Components\DataTable\SelectFilter;
use App\Models\Period\WarehouseSlip;
use Illuminate\Database\Eloquent\Builder;

/** @extends DataTableComponent<WarehouseSlip> */
class WarehouseSlipList extends DataTableComponent
{
    public string $model = WarehouseSlip::class;

    public string $sort = 'slip_date';

    public string $direction = 'desc';

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('warehouse_slips.view'), 403);
    }

    /** @return Builder<WarehouseSlip> */
    protected function baseQuery(): Builder
    {
        return WarehouseSlip::query()
            ->join('locations as l', 'l.id', '=', 'warehouse_slips.location_id')
            ->select(['warehouse_slips.*', 'l.name as location_name'])
            ->selectSub(
                fn ($query) => $query
                    ->from('warehouse_slip_lines')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('warehouse_slip_lines.warehouse_slip_id', 'warehouse_slips.id'),
                'line_count',
            );
    }

    /** @return list<Column> */
    public function columns(): array
    {
        return [
            Column::make('number', 'Numara')->searchable()->sortable(),
            Column::make('slip_date', 'Tarih')->sortable(),
            Column::make('location_name', 'Lokasyon'),
            Column::make('direction', 'Yön')->sortable(),
            Column::make('reason', 'Sebep')->sortable(),
            Column::make('line_count', 'Satır')->alignEnd(),
            Column::make('status', 'Durum')->sortable(),
        ];
    }

    /** @return list<SelectFilter|DateRangeFilter> */
    public function filters(): array
    {
        return [
            DateRangeFilter::make('slip_date', 'Tarih'),
            SelectFilter::make('direction', 'Yön')->options(['in' => 'Giriş', 'out' => 'Çıkış']),
            SelectFilter::make('status', 'Durum')->options([
                'draft' => 'Taslak',
                'posted' => 'Kesinleşti',
                'cancelled' => 'İptal',
            ]),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function rowActions(): array
    {
        return [['label' => 'Detay', 'method' => 'openSlip']];
    }

    public function openSlip(int|string $id): mixed
    {
        return $this->redirectRoute('stock.warehouse-slips.show', ['id' => $id], navigate: false);
    }

    /** @return array<string, mixed>|null */
    public function emptyAction(): ?array
    {
        return auth()->user()?->can('warehouse_slips.create')
            ? ['label' => 'Yeni Ambar Fişi', 'route' => 'stock.warehouse-slips.create']
            : null;
    }
}

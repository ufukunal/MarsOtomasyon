<?php

namespace App\Livewire\Pages\Stock;

use App\Livewire\Components\DataTable\Column;
use App\Livewire\Components\DataTable\DataTableComponent;
use App\Livewire\Components\DataTable\DateRangeFilter;
use App\Livewire\Components\DataTable\SelectFilter;
use App\Models\Period\StockCount;
use Illuminate\Database\Eloquent\Builder;

/** @extends DataTableComponent<StockCount> */
class StockCountList extends DataTableComponent
{
    public string $model = StockCount::class;
    public string $sort = 'count_date';
    public string $direction = 'desc';

    public function mount(): void { abort_unless(auth()->user()?->can('stock_counts.view'), 403); }

    /** @return Builder<StockCount> */
    protected function baseQuery(): Builder
    {
        return StockCount::query()
            ->join('locations as l', 'l.id', '=', 'stock_counts.location_id')
            ->select(['stock_counts.*', 'l.name as location_name'])
            ->selectSub(fn ($q) => $q->from('stock_count_lines')->selectRaw('COUNT(*)')->whereColumn('stock_count_lines.stock_count_id', 'stock_counts.id'), 'line_count')
            ->selectSub(fn ($q) => $q->from('stock_count_lines')->selectRaw('COUNT(*)')->whereColumn('stock_count_lines.stock_count_id', 'stock_counts.id')->where('difference', '<>', 0), 'difference_count');
    }

    /** @return list<Column> */
    public function columns(): array
    {
        return [
            Column::make('number', 'Numara')->searchable()->sortable(),
            Column::make('location_name', 'Lokasyon'),
            Column::make('count_date', 'Tarih')->sortable(),
            Column::make('status', 'Durum')->sortable(),
            Column::make('line_count', 'Satır')->alignEnd(),
            Column::make('difference_count', 'Farklı')->alignEnd(),
        ];
    }

    /** @return list<SelectFilter|DateRangeFilter> */
    public function filters(): array
    {
        return [
            DateRangeFilter::make('count_date', 'Tarih'),
            SelectFilter::make('status', 'Durum')->options([
                'draft'=>'Taslak','counting'=>'Sayım','review'=>'İnceleme','posted'=>'Kesinleşti','cancelled'=>'İptal',
            ]),
        ];
    }

    /** @return list<array<string,mixed>> */
    public function rowActions(): array { return [['label'=>'Detay','method'=>'openCount']]; }
    public function openCount(int|string $id): mixed { return $this->redirectRoute('stock.counts.show', ['id'=>$id], navigate:false); }
    /** @return array<string,mixed>|null */
    public function emptyAction(): ?array
    {
        return auth()->user()?->can('stock_counts.create') ? ['label'=>'Yeni Sayım','route'=>'stock.counts.create'] : null;
    }
}

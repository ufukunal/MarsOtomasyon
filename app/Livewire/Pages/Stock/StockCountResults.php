<?php

namespace App\Livewire\Pages\Stock;

use App\Livewire\Components\DataTable\Column;
use App\Livewire\Components\DataTable\DataTableComponent;
use App\Livewire\Components\DataTable\DateRangeFilter;
use App\Livewire\Components\DataTable\SelectFilter;
use App\Models\Period\Location;
use App\Models\Period\StockCountLine;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/** @extends DataTableComponent<StockCountLine> */
class StockCountResults extends DataTableComponent
{
    public string $model = StockCountLine::class;

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('stock_counts.view'), 403);
    }

    /** @return Builder<StockCountLine> */
    protected function baseQuery(): Builder
    {
        $rows = DB::connection('period')->table('stock_count_lines as line')
            ->join('stock_counts as count', 'count.id', '=', 'line.stock_count_id')
            ->join('products as product', 'product.id', '=', 'line.product_id')
            ->join('locations as location', 'location.id', '=', 'count.location_id')
            ->where('count.status', 'posted')
            ->select([
                'line.id',
                'line.stock_count_id',
                'line.product_id',
                'count.location_id',
                'count.number as count_number',
                'count.count_date',
                'location.name as location_name',
                'product.code as product_code',
                'product.name as product_name',
                'line.system_quantity',
                'line.counted_quantity',
                'line.difference',
                'line.is_approved',
                'line.note',
            ]);

        return StockCountLine::query()->fromSub($rows, 'stock_count_lines');
    }

    /** @return list<Column> */
    public function columns(): array
    {
        return [
            Column::make('count_number', 'Sayım No')->searchable()->sortable(),
            Column::make('count_date', 'Tarih')->sortable(),
            Column::make('location_name', 'Depo / Lokasyon')->searchable()->sortable(),
            Column::make('product_code', 'Ürün Kodu')->searchable()->sortable(),
            Column::make('product_name', 'Ürün')->searchable()->sortable(),
            Column::make('system_quantity', 'Sistem')->quantity()->sortable(),
            Column::make('counted_quantity', 'Sayılan')->quantity()->sortable(),
            Column::make('difference', 'Fark')->quantity()->sortable(),
            Column::make('is_approved', 'Onay')->sortable(),
        ];
    }

    /** @return list<SelectFilter|DateRangeFilter> */
    public function filters(): array
    {
        return [
            DateRangeFilter::make('count_date', 'Tarih'),
            SelectFilter::make('location_id', 'Depo / Lokasyon')->options(
                Location::query()->orderBy('name')->pluck('name', 'id')->all(),
            ),
        ];
    }

    public function columnUrl(Model $row, Column $column): ?string
    {
        if (in_array($column->key, ['product_code', 'product_name'], true)) {
            return auth()->user()?->can('products.view')
                ? route('products.show', ['product' => (int) $row->getAttribute('product_id')])
                : null;
        }

        if ($column->key === 'location_name') {
            return auth()->user()?->can('locations.view')
                ? route('locations.show', ['location' => (int) $row->getAttribute('location_id')])
                : null;
        }

        return $column->key === 'count_number'
            ? route('stock.counts.show', ['id' => (int) $row->getAttribute('stock_count_id')])
            : null;
    }
}

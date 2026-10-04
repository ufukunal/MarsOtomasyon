<?php

namespace App\Livewire\Pages\Stock;

use App\Livewire\Components\DataTable\Column;
use App\Livewire\Components\DataTable\DataTableComponent;
use App\Livewire\Components\DataTable\SelectFilter;
use App\Models\Period\Brand;
use App\Models\Period\Location;
use App\Models\Period\ProductCategory;
use App\Models\Period\StockBalance;
use App\Support\Formatting\TrFormatter;
use App\Support\Stock\StockStatusQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/** @extends DataTableComponent<StockBalance> */
class StockStatus extends DataTableComponent
{
    public string $model = StockBalance::class;

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('stock.view'), 403);
    }

    /** @return Builder<StockBalance> */
    protected function baseQuery(): Builder
    {
        return app(StockStatusQuery::class)->build($this->canViewCost());
    }

    /** @return list<Column> */
    public function columns(): array
    {
        $columns = [
            Column::make('product_label', 'Ürün')->searchable()->sortable(),
            Column::make('location_name', 'Lokasyon')->searchable()->sortable(),
            Column::make('quantity', 'Stok')->quantity()->sortable(),
            Column::make('reserved', 'Rezerve')->quantity()->sortable(),
            Column::make('consignment_reserved', 'Konsinye')->quantity()->sortable(),
            Column::make('quarantine', 'Karantina')->quantity()->sortable(),
            Column::make('available', 'Kullanılabilir')->quantity()->sortable(),
            Column::make('min_stock', 'Minimum')->quantity()->sortable(),
            Column::make('stock_status', 'Durum')->sortable(),
        ];

        if ($this->canViewCost()) {
            $columns[] = Column::make('moving_average', 'Birim Maliyet')->money()->sortable();
            $columns[] = Column::make('stock_value', 'Stok Değeri')->money()->sortable();
        }

        return $columns;
    }

    /** @return list<SelectFilter> */
    public function filters(): array
    {
        return [
            SelectFilter::make('location_id', 'Lokasyon')->options(
                Location::query()->orderBy('name')->pluck('name', 'id')->all(),
            ),
            SelectFilter::make('category_id', 'Kategori')->options(
                ProductCategory::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all(),
            ),
            SelectFilter::make('brand_id', 'Marka')->options(
                Brand::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all(),
            ),
            SelectFilter::make('stock_status', 'Durum')->options([
                'Tükendi' => 'Tükendi',
                'Kritik' => 'Kritik',
                'Yeterli' => 'Yeterli',
            ]),
            SelectFilter::make('has_stock', 'Stok')->options([
                1 => 'Yalnızca stoğu olanlar',
            ]),
        ];
    }

    /** @param Builder<StockBalance> $query */
    protected function applyFilter(Builder $query, mixed $filter, mixed $value): void
    {
        if ($filter instanceof SelectFilter && $filter->key === 'has_stock') {
            $query->whereRaw('available::numeric > 0');

            return;
        }

        parent::applyFilter($query, $filter, $value);
    }

    /** @return array<string, string> */
    public function summary(): array
    {
        $filtered = $this->query()->reorder()->toBase();
        $totals = DB::connection('period')
            ->query()
            ->fromSub($filtered, 'filtered')
            ->selectRaw(
                "COALESCE(SUM(
                    CASE WHEN product_kind <> 'set' THEN available::numeric ELSE 0 END
                ), 0)::text AS total_available"
            );

        if ($this->canViewCost()) {
            $totals->selectRaw(
                "COALESCE(SUM(
                    CASE
                        WHEN product_kind <> 'set' THEN COALESCE(stock_value::numeric, 0)
                        ELSE 0
                    END
                ), 0)::text AS total_value"
            );
        }

        $row = $totals->first();

        return array_filter([
            'product_label' => 'Toplam',
            'available' => TrFormatter::quantity((string) ($row->total_available ?? '0')),
            'stock_value' => $this->canViewCost()
                ? TrFormatter::money((string) ($row->total_value ?? '0'))
                : null,
        ], fn (?string $value): bool => $value !== null);
    }

    private function canViewCost(): bool
    {
        return auth()->user()?->can('cost.view') ?? false;
    }
}

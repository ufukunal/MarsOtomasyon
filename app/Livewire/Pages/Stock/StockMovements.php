<?php

namespace App\Livewire\Pages\Stock;

use App\Livewire\Components\DataTable\Column;
use App\Livewire\Components\DataTable\DataTableComponent;
use App\Livewire\Components\DataTable\DateRangeFilter;
use App\Livewire\Components\DataTable\SelectFilter;
use App\Models\Period\Location;
use App\Models\Period\Product;
use App\Models\Period\StockMovement;
use App\Support\Stock\StockMovementListQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Url;

/** @extends DataTableComponent<StockMovement> */
class StockMovements extends DataTableComponent
{
    public string $model = StockMovement::class;

    #[Url(as: 'product')]
    public ?int $productId = null;

    public string $sort = 'movement_date';

    public string $direction = 'desc';

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('stock.view'), 403);
    }

    /** @return Builder<StockMovement> */
    protected function baseQuery(): Builder
    {
        $query = app(StockMovementListQuery::class)->build($this->canViewCost());

        if ($this->productId) {
            $query->where('product_id', $this->productId);
        }

        return $query;
    }

    /** @return list<Column> */
    public function columns(): array
    {
        $columns = [
            Column::make('movement_date', 'Tarih')->sortable(),
            Column::make('product_label', 'Ürün')->searchable()->sortable(),
            Column::make('location_name', 'Lokasyon')->searchable()->sortable(),
            Column::make('direction_label', 'Yön')->sortable(),
            Column::make('reason_label', 'Sebep')->sortable(),
            Column::make('quantity', 'Miktar')->quantity()->sortable(),
        ];

        if ($this->canViewCost()) {
            $columns[] = Column::make('unit_cost', 'Birim Maliyet')->money()->sortable();
            $columns[] = Column::make('total_cost', 'Tutar')->money()->sortable();
        }

        $columns[] = Column::make('balance_after', 'Kalan Bakiye')->quantity()->sortable();
        $columns[] = Column::make('document_no', 'Belge No')->searchable();
        $columns[] = Column::make('created_by_name', 'Kullanıcı')->searchable()->sortable();

        return $columns;
    }

    /** @return list<SelectFilter|DateRangeFilter> */
    public function filters(): array
    {
        return [
            DateRangeFilter::make('movement_date', 'Tarih'),
            SelectFilter::make('product_id', 'Ürün')->options(
                Product::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all(),
            ),
            SelectFilter::make('location_id', 'Lokasyon')->options(
                Location::query()->orderBy('name')->pluck('name', 'id')->all(),
            ),
            SelectFilter::make('direction', 'Yön')->options([
                'in' => 'Giriş',
                'out' => 'Çıkış',
            ]),
            SelectFilter::make('reason', 'Sebep')->options([
                'purchase' => 'Alış',
                'sale' => 'Satış',
                'transfer' => 'Transfer',
                'count' => 'Sayım',
                'production' => 'Üretim',
                'production_consumption' => 'Üretim Tüketimi',
                'return' => 'İade',
                'sales_return' => 'Satış İadesi',
                'purchase_return' => 'Alış İadesi',
                'scrap' => 'Hurda',
                'opening' => 'Açılış',
                'adjustment' => 'Düzeltme',
            ]),
            SelectFilter::make('document_type', 'Belge Türü')->options(
                StockMovement::query()
                    ->whereNotNull('document_type')
                    ->distinct()
                    ->orderBy('document_type')
                    ->pluck('document_type', 'document_type')
                    ->all(),
            ),
            SelectFilter::make('created_by', 'Kullanıcı')->options(
                StockMovement::query()
                    ->whereNotNull('created_by')
                    ->whereNotNull('created_by_name')
                    ->distinct()
                    ->orderBy('created_by_name')
                    ->pluck('created_by_name', 'created_by')
                    ->all(),
            ),
        ];
    }

    public function columnUrl(Model $row, Column $column): ?string
    {
        if ($column->key !== 'document_no'
            || ! $row->getAttribute('document_type')
            || ! $row->getAttribute('document_id')) {
            return null;
        }

        $route = match ((string) $row->getAttribute('document_type')) {
            'transfer' => 'stock.transfers.show',
            'warehouse_slip' => 'stock.warehouse-slips.show',
            'stock_count' => 'stock.counts.show',
            default => null,
        };

        if ($route === null || ! Route::has($route)) {
            return null;
        }

        return route($route, ['id' => (int) $row->getAttribute('document_id')]);
    }

    private function canViewCost(): bool
    {
        return auth()->user()?->can('cost.view') ?? false;
    }
}

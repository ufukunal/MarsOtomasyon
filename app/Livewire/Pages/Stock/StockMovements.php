<?php

namespace App\Livewire\Pages\Stock;

use App\Livewire\Components\DataTable\Column;
use App\Livewire\Components\DataTable\DataTableComponent;
use App\Livewire\Components\DataTable\DateRangeFilter;
use App\Livewire\Components\DataTable\SelectFilter;
use App\Models\Period\Location;
use App\Models\Period\Product;
use App\Models\Period\StockMovement;
use App\Support\Formatting\BusinessLabelFormatter;
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
            Column::make('reason_label', 'Hareket')->sortable(),
            Column::make('document_no', 'Kaynak Belge')->searchable(),
            Column::make('product_code', 'Ürün Kodu')->searchable()->sortable(),
            Column::make('product_label', 'Ürün Adı')->searchable()->sortable(),
            Column::make('location_name', 'Depo / Lokasyon')->searchable()->sortable(),
            Column::make('direction_label', 'Yön')->sortable(),
            Column::make('quantity', 'Miktar')->quantity()->sortable(),
        ];

        if ($this->canViewCost()) {
            $columns[] = Column::make('unit_cost', 'Birim Maliyet')->money()->sortable();
            $columns[] = Column::make('total_cost', 'Tutar')->money()->sortable();
        }

        $columns[] = Column::make('balance_after', 'Kalan Bakiye')->quantity()->sortable();
        $columns[] = Column::make('created_by_name', 'İşlemi Yapan')->searchable()->sortable();

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
                    ->pluck('document_type')
                    ->mapWithKeys(fn ($type) => [(string) $type => BusinessLabelFormatter::documentType($type)])
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
        if (in_array($column->key, ['product_code', 'product_label'], true)) {
            return auth()->user()?->can('products.view')
                ? route('products.show', ['product' => (int) $row->getAttribute('product_id')])
                : null;
        }

        if ($column->key === 'reason_label' || $column->key === 'movement_date') {
            return route('stock.movements.show', ['id' => $row->getKey()]);
        }

        if ($column->key !== 'document_no' || blank($row->getAttribute('document_no'))) {
            return null;
        }

        // Even an unsupported or unauthorized source document has a readable
        // stock-movement detail, so the visible document number remains clickable.
        if (! $row->getAttribute('document_type') || ! $row->getAttribute('document_id')) {
            return route('stock.movements.show', ['id' => $row->getKey()]);
        }

        [$route, $permission] = match ((string) $row->getAttribute('document_type')) {
            'transfer' => ['stock.transfers.show', 'transfers.view'],
            'warehouse_slip' => ['stock.warehouse-slips.show', 'warehouse_slips.view'],
            'stock_count' => ['stock.counts.show', 'stock_counts.view'],
            default => [null, null],
        };

        if ($route === null || ! Route::has($route)
            || ! auth()->user()?->can($permission)) {
            return route('stock.movements.show', ['id' => $row->getKey()]);
        }

        return route($route, ['id' => (int) $row->getAttribute('document_id')]);
    }

    /** @return list<array<string, mixed>> */
    public function rowActions(): array
    {
        // Records open via their linked identifiers, not redundant row buttons.
        return [];
    }

    public function showMovement(int|string $id): mixed
    {
        return $this->redirectRoute('stock.movements.show', ['id' => $id], navigate: false);
    }

    private function canViewCost(): bool
    {
        return auth()->user()?->can('cost.view') ?? false;
    }
}

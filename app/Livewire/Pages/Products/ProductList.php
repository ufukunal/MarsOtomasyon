<?php

namespace App\Livewire\Pages\Products;

use App\Enums\ProductKind;
use App\Livewire\Components\DataTable\Column;
use App\Livewire\Components\DataTable\DataTableComponent;
use App\Livewire\Components\DataTable\DateRangeFilter;
use App\Livewire\Components\DataTable\SelectFilter;
use App\Models\Period\Brand;
use App\Models\Period\Product;
use App\Models\Period\ProductCategory;
use App\Models\Period\StockBalance;
use App\Support\Formatting\TrFormatter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** @extends DataTableComponent<Product> */
class ProductList extends DataTableComponent
{
    public string $model = Product::class;

    public function mount(): void
    {
        $this->authorize('viewAny', Product::class);
    }

    /** @return Builder<Product> */
    protected function baseQuery(): Builder
    {
        $query = Product::query()->with(['brand', 'category', 'unit'])
            ->select('products.*');

        if (auth()->user()?->can('stock.view')) {
            $query
                ->selectSub(
                    StockBalance::query()
                        ->selectRaw('COALESCE(SUM(quantity), 0)')
                        ->whereColumn('stock_balances.product_id', 'products.id'),
                    'on_hand_v65',
                )
                ->selectSub(
                    StockBalance::query()
                        ->selectRaw('COALESCE(SUM(reserved + consignment_reserved), 0)')
                        ->whereColumn('stock_balances.product_id', 'products.id'),
                    'reserved_v65',
                )
                ->selectSub(
                    StockBalance::query()
                        ->selectRaw('COALESCE(SUM(quantity - reserved - consignment_reserved - quarantine), 0)')
                        ->whereColumn('stock_balances.product_id', 'products.id'),
                    'available_v65',
                );
        }

        return $query;
    }

    /** @return list<Column> */
    public function columns(): array
    {
        // The approved v65 grid keeps separate stock, order reservation and available columns.
        $columns = [
            Column::make('code', 'Ürün Kodu')->searchable()->sortable(),
            Column::make('name', 'Ürün Adı')->searchable()->sortable(),
            Column::make('brand_name', 'Marka'),
            Column::make('category_name', 'Kategori'),
            Column::make('unit_name', 'Birim'),
            Column::make('list_price', 'Satış Fiyatı')->money()->sortable(),
        ];

        if (auth()->user()?->can('stock.view')) {
            $columns[] = Column::make('on_hand_v65', 'Stok')->quantity()->sortable();
            $columns[] = Column::make('reserved_v65', 'Sipariş Rez.')->quantity()->sortable();
            $columns[] = Column::make('available_v65', 'Kullanılabilir')->quantity()->sortable();
        }

        $columns[] = Column::make('is_active', 'Durum')->sortable();

        return $columns;
    }

    public function formattedValue(Model $row, Column $column): string
    {
        if ($column->key === 'is_active') {
            return $row->getAttribute('is_active') ? 'Aktif' : 'Pasif';
        }

        if (in_array($column->key, ['on_hand_v65', 'reserved_v65', 'available_v65'], true)) {
            $value = $column->key === 'available_v65'
                && $row instanceof Product
                && $row->kind === ProductKind::Set
                    ? $row->setAvailability()
                    : $row->getAttribute($column->key);

            return TrFormatter::quantity((string) ($value ?? '0')).' '.$row->getAttribute('unit_name');
        }

        return parent::formattedValue($row, $column);
    }

    /** @return list<SelectFilter|DateRangeFilter> */
    public function filters(): array
    {
        return [
            SelectFilter::make('category_id', 'Kategori')->options(
                ProductCategory::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all()
            ),
            SelectFilter::make('brand_id', 'Marka')->options(
                Brand::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all()
            ),
            SelectFilter::make('kind', 'Tip')->options([
                'normal' => 'Normal',
                'set' => 'Set',
                'configurable' => 'Konfigüre',
            ]),
            SelectFilter::make('channel_stock_mode', 'Stok Tipi')->options([
                'stock' => 'Stok',
                'production' => 'Üretim',
                'manual' => 'Manuel',
            ]),
            SelectFilter::make('is_active', 'Durum')->options([1 => 'Aktif', 0 => 'Pasif']),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function rowActions(): array
    {
        $actions = [['label' => 'Detay', 'method' => 'showProduct']];

        if (auth()->user()?->can('products.update')) {
            $actions[] = ['label' => 'Düzenle', 'method' => 'editProduct'];
        }

        return $actions;
    }

    public function columnUrl(Model $row, Column $column): ?string
    {
        return in_array($column->key, ['code', 'name'], true)
            ? route('products.show', ['product' => $row->getKey()])
            : null;
    }

    public function showProduct(int|string $id): mixed
    {
        return $this->redirectRoute('products.show', ['product' => $id], navigate: false);
    }

    public function editProduct(int|string $id): mixed
    {
        return $this->redirectRoute('products.edit', ['product' => $id], navigate: false);
    }

    /** @return array<string, mixed>|null */
    public function emptyAction(): ?array
    {
        return auth()->user()?->can('products.create')
            ? ['label' => 'Yeni Ürün', 'route' => 'products.create']
            : null;
    }
}

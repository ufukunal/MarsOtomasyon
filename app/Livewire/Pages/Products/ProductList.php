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
        return Product::query()->with(['brand', 'category', 'unit']);
    }

    /** @return list<Column> */
    public function columns(): array
    {
        // v65 sırası; veri modelinde olmayan tedarikçi ürün kodu, alış KDV,
        // ikinci/üçüncü kategori ve model için sahte sütun oluşturulmaz.
        return [
            Column::make('code', 'Ürün Kodu')->searchable()->sortable(),
            Column::make('name', 'Ürün')->searchable()->sortable(),
            Column::make('barcode', 'Barkodu')->searchable()->sortable(),
            Column::make('kind', 'Ürün Tipi')->sortable(),
            Column::make('vat_rate', 'Satış KDV %')->alignEnd()->sortable(),
            Column::make('category_name', '1. Kategori'),
            Column::make('unit_name', 'Birim'),
            Column::make('brand_name', 'Marka'),
            Column::make('list_price', 'Satış Fiyatı')->money()->sortable(),
            Column::make('available_quantity', 'Kullanılabilir')->quantity(),
            Column::make('is_active', 'Durum')->sortable(),
        ];
    }

    public function formattedValue(Model $row, Column $column): string
    {
        if ($column->key === 'is_active') {
            return $row->getAttribute('is_active') ? 'Aktif' : 'Pasif';
        }

        if ($column->key === 'vat_rate') {
            return '%'.TrFormatter::money((string) $row->getAttribute('vat_rate'));
        }

        if ($column->key === 'kind') {
            $kind = $row->getAttribute('kind');

            if ($kind instanceof ProductKind) {
                return match ($kind) {
                    ProductKind::Normal => 'Normal',
                    ProductKind::Set => 'Set',
                    ProductKind::Configurable => 'Konfigüre',
                };
            }
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

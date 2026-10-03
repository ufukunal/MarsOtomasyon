<?php

namespace App\Livewire\Pages\Products;

use App\Livewire\Components\DataTable\Column;
use App\Livewire\Components\DataTable\DataTableComponent;
use App\Livewire\Components\DataTable\SelectFilter;
use App\Models\Period\Brand;
use App\Models\Period\Product;
use App\Models\Period\ProductCategory;
use Illuminate\Database\Eloquent\Builder;

class ProductList extends DataTableComponent
{
    public string $model = Product::class;

    public function mount(): void
    {
        $this->authorize('viewAny', Product::class);
    }

    /** @return \Illuminate\Database\Eloquent\Builder<\App\Models\Period\Product> */
    protected function baseQuery(): Builder
    {
        return Product::query()->with(['brand', 'category', 'unit']);
    }

    /** @return list<\App\Livewire\Components\DataTable\Column> */
    public function columns(): array
    {
        return [
            Column::make('code', 'Kod')->searchable()->sortable(),
            Column::make('name', 'Ad')->searchable()->sortable(),
            Column::make('brand_name', 'Marka'),
            Column::make('category_name', 'Kategori'),
            Column::make('unit_name', 'Birim'),
            Column::make('list_price', 'Liste Fiyatı')->money()->sortable(),
            Column::make('vat_rate', 'KDV %')->alignEnd(),
            Column::make('available_quantity', 'Stok')->quantity(),
            Column::make('kind', 'Tip')->sortable(),
            Column::make('is_active', 'Durum')->sortable(),
        ];
    }

    /** @return list<\App\Livewire\Components\DataTable\SelectFilter|\App\Livewire\Components\DataTable\DateRangeFilter> */
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
            SelectFilter::make('is_active', 'Durum')->options([1 => 'Aktif', 0 => 'Pasif']),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function rowActions(): array
    {
        return auth()->user()?->can('products.update')
            ? [['label' => 'Düzenle', 'method' => 'editProduct']]
            : [];
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

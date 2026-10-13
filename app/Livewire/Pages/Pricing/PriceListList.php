<?php

namespace App\Livewire\Pages\Pricing;

use App\Livewire\Components\DataTable\Column;
use App\Livewire\Components\DataTable\DataTableComponent;
use App\Models\Period\PriceList;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** @extends DataTableComponent<PriceList> */
class PriceListList extends DataTableComponent
{
    public string $model = PriceList::class;

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('price_lists.view'), 403);
    }

    /** @return Builder<PriceList> */
    protected function baseQuery(): Builder
    {
        return PriceList::query()->withCount('items');
    }

    /** @return list<Column> */
    public function columns(): array
    {
        return [
            Column::make('name', 'Ad')->sortable(),
            Column::make('currency', 'Para Birimi')->sortable(),
            Column::make('vat_included', 'Giriş KDV Dahil')->sortable(),
            Column::make('is_default', 'Varsayılan')->sortable(),
            Column::make('items_count', 'Ürün Sayısı')->alignEnd(),
            Column::make('is_active', 'Durum')->sortable(),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function rowActions(): array
    {
        // Records open via their linked identifiers, not redundant row buttons.
        return [];
    }

    public function columnUrl(Model $row, Column $column): ?string
    {
        return $column->key === 'name'
            ? route('price-lists.detail', ['list' => $row->getKey()])
            : null;
    }

    public function editList(int|string $id): mixed
    {
        return $this->redirectRoute('price-lists.detail', ['list' => $id], navigate: false);
    }

    /** @return array<string, mixed>|null */
    public function emptyAction(): ?array
    {
        return auth()->user()?->can('price_lists.create')
            ? ['label' => 'Yeni Fiyat Listesi', 'route' => 'price-lists.detail']
            : null;
    }
}

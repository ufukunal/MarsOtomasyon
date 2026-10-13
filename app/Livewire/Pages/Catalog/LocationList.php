<?php

namespace App\Livewire\Pages\Catalog;

use App\Livewire\Components\DataTable\Column;
use App\Livewire\Components\DataTable\DataTableComponent;
use App\Livewire\Components\DataTable\DateRangeFilter;
use App\Livewire\Components\DataTable\SelectFilter;
use App\Models\Period\Location;
use Illuminate\Database\Eloquent\Model;

/** @extends DataTableComponent<Location> */
class LocationList extends DataTableComponent
{
    public string $model = Location::class;

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('locations.view'), 403);
    }

    /** @return list<Column> */
    public function columns(): array
    {
        return [
            Column::make('code', 'Kod')->searchable()->sortable(),
            Column::make('name', 'Ad')->searchable()->sortable(),
            Column::make('kind', 'Tip')->sortable(),
            Column::make('plate', 'Plaka'),
            Column::make('is_default', 'Varsayılan')->sortable(),
            Column::make('is_active', 'Durum')->sortable(),
        ];
    }

    /** @return list<SelectFilter|DateRangeFilter> */
    public function filters(): array
    {
        return [
            SelectFilter::make('kind', 'Tip')->options([
                'warehouse' => 'Depo',
                'branch' => 'Şube',
                'vehicle' => 'Araç',
                'subcontractor' => 'Fason',
            ]),
            SelectFilter::make('is_active', 'Durum')->options([
                1 => 'Aktif',
                0 => 'Pasif',
            ]),
        ];
    }

    /** @return array<string, mixed>|null */
    public function emptyAction(): ?array
    {
        return auth()->user()?->can('locations.create')
            ? ['label' => 'Yeni Depo / Lokasyon', 'route' => 'locations.create']
            : null;
    }

    /** @return list<array<string, mixed>> */
    public function rowActions(): array
    {
        // Records open via their linked identifiers, not redundant row buttons.
        return [];
    }

    public function columnUrl(Model $row, Column $column): ?string
    {
        return in_array($column->key, ['code', 'name'], true)
            ? route('locations.show', ['location' => $row->getKey()])
            : null;
    }

    public function showLocation(int|string $id): mixed
    {
        return $this->redirectRoute('locations.show', ['location' => $id], navigate: false);
    }

    public function openLocation(int|string $id): mixed
    {
        return $this->redirectRoute('locations.edit', ['location' => $id], navigate: false);
    }
}

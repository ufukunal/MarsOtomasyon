<?php

namespace App\Livewire\Pages\Catalog;

use App\Livewire\Components\DataTable\Column;
use App\Livewire\Components\DataTable\DataTableComponent;
use App\Models\Period\Unit;
use Illuminate\Database\Eloquent\Model;

/** @extends DataTableComponent<Unit> */
class UnitList extends DataTableComponent
{
    public string $model = Unit::class;

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('units.view'), 403);
    }

    /** @return list<Column> */
    public function columns(): array
    {
        return [
            Column::make('code', 'Kod')->searchable()->sortable(),
            Column::make('name', 'Ad')->searchable()->sortable(),
            Column::make('is_base', 'Temel')->sortable(),
            Column::make('is_active', 'Durum')->sortable(),
        ];
    }

    /** @return array<string,mixed>|null */
    public function emptyAction(): ?array
    {
        return auth()->user()?->can('units.create')
            ? ['label' => 'Yeni Birim', 'route' => 'units.create']
            : null;
    }

    /** @return list<array<string,mixed>> */
    public function rowActions(): array
    {
        // Records open via their linked identifiers, not redundant row buttons.
        return [];
    }

    public function columnUrl(Model $row, Column $column): ?string
    {
        return in_array($column->key, ['code', 'name'], true) && auth()->user()?->can('units.update')
            ? route('units.edit', ['unit' => $row->getKey()])
            : null;
    }

    public function editUnit(int|string $id): mixed
    {
        abort_unless(auth()->user()?->can('units.update'), 403);

        return $this->redirectRoute('units.edit', ['unit' => $id], navigate: false);
    }
}

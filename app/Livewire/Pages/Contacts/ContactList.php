<?php

namespace App\Livewire\Pages\Contacts;

use App\Livewire\Components\DataTable\Column;
use App\Livewire\Components\DataTable\DataTableComponent;
use App\Livewire\Components\DataTable\SelectFilter;
use App\Models\Period\Contact;

class ContactList extends DataTableComponent
{
    public string $model = Contact::class;

    public function mount(): void
    {
        $this->authorize('viewAny', Contact::class);
    }

    public function columns(): array
    {
        return [
            Column::make('code', 'Kod')->searchable()->sortable(),
            Column::make('title', 'Unvan')->searchable()->sortable(),
            Column::make('city', 'İl')->searchable()->sortable(),
            Column::make('risk_limit', 'Risk Limiti')->money()->sortable(),
            Column::make('is_active', 'Durum')->sortable(),
        ];
    }

    public function filters(): array
    {
        return [
            SelectFilter::make('is_active', 'Durum')->options([
                1 => 'Aktif',
                0 => 'Pasif',
            ]),
        ];
    }
}

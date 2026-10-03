<?php

namespace App\Livewire\Pages\Catalog;

use App\Actions\Locations\SaveLocation;
use App\Models\Period\Location;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class LocationForm extends Component
{
    public ?Location $location = null;
    public string $code = '';
    public string $name = '';
    public string $kind = 'warehouse';
    public string $plate = '';
    public string $address = '';
    public bool $isDefault = false;
    public bool $isActive = true;
    public int $version = 1;

    public function mount(?Location $location = null): void
    {
        $this->location = $location;

        abort_unless(auth()->user()?->can($location ? 'locations.update' : 'locations.create'), 403);

        if ($location) {
            $this->code = $location->code;
            $this->name = $location->name;
            $this->kind = $location->kind->value;
            $this->plate = (string) $location->plate;
            $this->address = (string) $location->address;
            $this->isDefault = (bool) $location->is_default;
            $this->isActive = (bool) $location->is_active;
            $this->version = (int) $location->version;
        }
    }

    public function save(SaveLocation $action): void
    {
        $data = $this->validate([
            'code' => ['required', 'max:20'],
            'name' => ['required', 'max:255'],
            'kind' => ['required', 'in:warehouse,branch,vehicle'],
            'plate' => ['nullable', 'max:20'],
            'address' => ['nullable'],
            'isDefault' => ['boolean'],
            'isActive' => ['boolean'],
        ]);

        $saved = $action->handle([
            'code' => $data['code'],
            'name' => $data['name'],
            'kind' => $data['kind'],
            'plate' => $data['plate'],
            'address' => $data['address'],
            'is_default' => $data['isDefault'],
            'is_active' => $data['isActive'],
        ], $this->location, $this->version);

        $this->redirectRoute('locations.edit', ['location' => $saved->id], navigate: false);
    }

    public function render(): View
    {
        return view('livewire.pages.catalog.location-form')
            ->layout('layouts.app', ['pageTitle' => $this->location ? 'Lokasyon Düzenle' : 'Yeni Lokasyon']);
    }
}

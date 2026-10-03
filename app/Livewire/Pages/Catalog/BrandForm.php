<?php

namespace App\Livewire\Pages\Catalog;

use App\Actions\Catalog\SaveBrand;
use App\Models\Period\Brand;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class BrandForm extends Component
{
    public ?Brand $brand = null;
    public string $name = '';
    public bool $isActive = true;
    public int $version = 1;

    public function mount(?Brand $brand = null): void
    {
        abort_unless(auth()->user()?->can($brand ? 'brands.update' : 'brands.create'), 403);
        $this->brand = $brand;

        if ($brand) {
            $this->name = $brand->name;
            $this->isActive = (bool) $brand->is_active;
            $this->version = (int) $brand->version;
        }
    }

    public function save(SaveBrand $action): void
    {
        $this->validate(['name' => ['required', 'max:255']]);

        $this->brand = $action->handle([
            'name' => $this->name,
            'is_active' => $this->isActive,
        ], $this->brand, $this->version);

        $this->version = (int) $this->brand->version;
    }

    public function render(): View
    {
        return view('livewire.pages.catalog.brand-form')
            ->layout('layouts.app', ['pageTitle' => $this->brand ? 'Marka Düzenle' : 'Yeni Marka']);
    }
}

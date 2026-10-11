<?php

namespace App\Livewire\Pages\Catalog;

use App\Models\Period\Location;
use App\Models\Period\StockBalance;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class LocationDetail extends Component
{
    public Location $location;

    public function mount(Location $location): void
    {
        abort_unless(auth()->user()?->can('locations.view'), 403);
        $this->location = $location;
    }

    public function render(): View
    {
        abort_unless(auth()->user()?->can('locations.view'), 403);

        $balances = auth()->user()->can('stock.view')
            ? StockBalance::query()
                ->where('location_id', $this->location->id)
                ->with('product')
                ->orderBy('product_id')
                ->limit(100)
                ->get()
            : collect();

        return view('livewire.pages.catalog.location-detail', [
            'balances' => $balances,
        ])->layout('layouts.app', [
            'pageTitle' => 'Depo / Lokasyon · '.$this->location->code,
        ]);
    }
}

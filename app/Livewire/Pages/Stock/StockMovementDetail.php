<?php

namespace App\Livewire\Pages\Stock;

use App\Models\Period\StockMovement;
use App\Support\Documents\DocumentEditNavigation;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class StockMovementDetail extends Component
{
    public StockMovement $movement;

    public function mount(int $id): void
    {
        abort_unless(auth()->user()?->can('stock.view'), 403);
        $this->movement = StockMovement::query()
            ->with(['product', 'location'])
            ->findOrFail($id);
    }

    public function render(): View
    {
        abort_unless(auth()->user()?->can('stock.view'), 403);

        $sourceUrl = DocumentEditNavigation::url(
            (string) $this->movement->document_type,
            $this->movement->document_id,
        );

        return view('livewire.pages.stock.stock-movement-detail', [
            'sourceUrl' => $sourceUrl,
            'canViewCost' => auth()->user()->can('cost.view'),
        ])->layout('layouts.app', [
            'pageTitle' => 'Stok Hareketi · #'.$this->movement->id,
        ]);
    }
}

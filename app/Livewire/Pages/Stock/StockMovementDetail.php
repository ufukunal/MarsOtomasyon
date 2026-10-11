<?php

namespace App\Livewire\Pages\Stock;

use App\Models\Period\StockMovement;
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

        $sourceRoute = match ((string) $this->movement->document_type) {
            'transfer' => 'stock.transfers.show',
            'warehouse_slip' => 'stock.warehouse-slips.show',
            'stock_count' => 'stock.counts.show',
            default => null,
        };
        $sourcePermission = match ((string) $this->movement->document_type) {
            'transfer' => 'transfers.view',
            'warehouse_slip' => 'warehouse_slips.view',
            'stock_count' => 'stock_counts.view',
            default => null,
        };
        $sourceUrl = $sourceRoute && $sourcePermission
            && $this->movement->document_id
            && auth()->user()?->can($sourcePermission)
            ? route($sourceRoute, ['id' => $this->movement->document_id])
            : null;

        return view('livewire.pages.stock.stock-movement-detail', [
            'sourceUrl' => $sourceUrl,
            'canViewCost' => auth()->user()?->can('cost.view') ?? false,
        ])->layout('layouts.app', [
            'pageTitle' => 'Stok Hareketi · #'.$this->movement->id,
        ]);
    }
}

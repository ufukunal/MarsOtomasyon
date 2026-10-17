<?php

namespace App\Livewire\Pages\Stock;

use App\Models\Period\StockReservation;
use App\Support\Documents\DocumentEditNavigation;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class ReservationDetail extends Component
{
    public StockReservation $reservation;

    public function mount(int $id): void
    {
        abort_unless(auth()->user()?->can('reservations.view'), 403);
        $this->reservation = StockReservation::query()
            ->with(['product', 'location'])->findOrFail($id);
    }

    public function render(): View
    {
        abort_unless(auth()->user()?->can('reservations.view'), 403);

        return view('livewire.pages.stock.reservation-detail', [
            'sourceUrl' => DocumentEditNavigation::url(
                (string) $this->reservation->document_type,
                $this->reservation->document_id,
            ),
        ])->layout('layouts.app', [
                'pageTitle' => 'Stok Rezervasyonu · #'.$this->reservation->id,
            ]);
    }
}

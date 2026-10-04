<?php

namespace App\Livewire\Purchases;

use App\Models\Period\Contact;
use App\Queries\Purchases\BuildSupplierPerformance;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class SupplierPerformance extends Component
{
    public ?int $contactId = null;

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('supplier_performance.view'), 403);
    }

    public function render(BuildSupplierPerformance $query): View
    {
        return view('livewire.purchases.supplier-performance', [
            'contacts' => Contact::query()->where('is_active', true)->orderBy('title')->limit(500)->get(),
            'metrics' => $this->contactId ? $query->handle($this->contactId) : null,
        ])->layout('layouts.app', ['pageTitle' => 'Tedarikçi Performansı']);
    }
}

<?php

namespace App\Livewire\Finance;

use App\Models\Period\Contact;
use App\Models\Period\ContactTransaction;
use App\Queries\Finance\BuildContactAging;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class ContactAging extends Component
{
    public ?int $contactId = null;

    public string $asOf = '';

    public string $currency = 'TRY';

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('contact_aging.view'), 403);
        $this->asOf = now()->toDateString();
    }

    public function render(BuildContactAging $query): View
    {
        $currencies = $this->contactId
            ? ContactTransaction::query()->where('contact_id', $this->contactId)
                ->distinct()->orderBy('currency')->pluck('currency')->all()
            : [];
        $currency = in_array($this->currency, $currencies, true)
            ? $this->currency : ($currencies[0] ?? 'TRY');

        return view('livewire.finance.contact-aging', [
            'contacts' => Contact::query()->where('is_active', true)->orderBy('title')->limit(500)->get(),
            'currencies' => $currencies,
            'aging' => $this->contactId ? $query->handle($this->contactId, $this->asOf, $currency) : null,
        ])->layout('layouts.app', ['pageTitle' => 'Cari Yaşlandırma']);
    }
}

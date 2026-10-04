<?php

namespace App\Livewire\Finance;

use App\Models\Period\Contact;
use App\Queries\Finance\BuildContactAging;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class ContactAging extends Component
{
    public ?int $contactId = null;
    public string $asOf = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('contact_aging.view'), 403);
        $this->asOf = now()->toDateString();
    }

    public function render(BuildContactAging $query): View
    {
        return view('livewire.finance.contact-aging', [
            'contacts' => Contact::query()->where('is_active', true)->orderBy('title')->limit(500)->get(),
            'aging' => $this->contactId ? $query->handle($this->contactId, $this->asOf) : null,
        ])->layout('layouts.app', ['pageTitle' => 'Cari Yaşlandırma']);
    }
}

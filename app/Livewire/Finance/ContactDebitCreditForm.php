<?php

namespace App\Livewire\Finance;

use App\Actions\Finance\PostContactDebitCredit;
use App\Livewire\Concerns\WithIdempotentMutations;
use App\Models\Period\Contact;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class ContactDebitCreditForm extends Component
{
    use WithIdempotentMutations;

    public ?int $contactId = null;

    public string $direction = 'debit';

    public string $amount = '0.0000';

    public string $documentDate = '';

    public string $reason = '';

    public string $note = '';

    public ?int $postedDocumentId = null;

    public function mount(): void
    {
        $this->seedMutationKeys(['post']);
        abort_unless(auth()->user()?->can('contacts.update'), 403);
        $this->documentDate = now()->toDateString();
    }

    public function post(PostContactDebitCredit $action): void
    {
        $document = $action->handle(
            (int) $this->contactId,
            $this->direction,
            $this->amount,
            $this->documentDate,
            $this->reason,
            $this->mutationKey('post'),
            $this->note !== '' ? $this->note : null,
        );
        $this->completeMutation('post');
        $this->postedDocumentId = (int) $document->id;
    }

    public function render(): View
    {
        return view('livewire.finance.contact-debit-credit-form', [
            'contacts' => Contact::query()->where('is_active', true)->orderBy('title')->limit(500)->get(),
        ])->layout('layouts.app', ['pageTitle' => 'Cari Borç / Alacak Fişi']);
    }
}

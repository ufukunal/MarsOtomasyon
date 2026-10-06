<?php

namespace App\Livewire\Purchases;

use App\Actions\Documents\ReverseDocument;
use App\Actions\Purchases\PostPayment;
use App\Enums\DocumentType;
use App\Livewire\Concerns\WithIdempotentMutations;
use App\Models\Period\BankAccount;
use App\Models\Period\CashAccount;
use App\Models\Period\Contact;
use App\Models\Period\Document;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class PaymentForm extends Component
{
    use WithIdempotentMutations;

    public ?int $contactId = null;

    public string $amount = '0.0000';

    public string $currency = 'TRY';

    public string $exchangeRate = '1.000000';

    public string $accountType = 'cash';

    public ?int $accountId = null;

    public string $documentDate = '';

    public ?int $sourceInvoiceId = null;

    public string $note = '';

    public ?int $postedDocumentId = null;

    public string $reversalDate = '';

    public string $reversalReason = '';

    public function mount(): void
    {
        $this->seedMutationKeys(['post', 'reverse']);
        abort_unless(auth()->user()?->can('payments.view'), 403);
        $this->documentDate = now()->toDateString();
        $this->reversalDate = now()->toDateString();
    }

    public function post(PostPayment $action): void
    {
        $document = $action->handle(
            (int) $this->contactId,
            $this->amount,
            $this->currency,
            $this->exchangeRate,
            $this->documentDate,
            $this->accountType,
            (int) $this->accountId,
            $this->mutationKey('post'),
            $this->sourceInvoiceId,
            $this->note !== '' ? $this->note : null,
        );
        $this->completeMutation('post');
        $this->postedDocumentId = (int) $document->id;
    }

    public function reverse(ReverseDocument $action): void
    {
        abort_unless($this->postedDocumentId !== null, 422);
        $document = Document::query()->findOrFail($this->postedDocumentId);
        $reversal = $action->handle(
            $document,
            $this->reversalDate,
            $this->reversalReason,
            $this->mutationKey('reverse'),
        );
        $this->completeMutation('reverse');
        $this->postedDocumentId = (int) $reversal->id;
    }

    public function render(): View
    {
        return view('livewire.purchases.payment-form', [
            'contacts' => Contact::query()->where('is_active', true)->orderBy('title')->limit(500)->get(),
            'cashAccounts' => CashAccount::query()->where('is_active', true)->orderBy('name')->get(),
            'bankAccounts' => BankAccount::query()->where('is_active', true)->orderBy('bank_name')->get(),
            'invoices' => Document::query()
                ->where('document_type', DocumentType::SupplierInvoice->value)
                ->where('status', 'posted')
                ->orderByDesc('document_date')
                ->limit(500)
                ->get(),
        ])->layout('layouts.app', ['pageTitle' => 'Ödeme']);
    }
}

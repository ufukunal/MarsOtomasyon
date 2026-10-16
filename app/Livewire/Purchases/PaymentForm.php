<?php

namespace App\Livewire\Purchases;

use App\Actions\Documents\ReverseDocument;
use App\Actions\Purchases\PostPayment;
use App\Enums\DocumentType;
use App\Livewire\Concerns\WithFinancialRecordCorrection;
use App\Livewire\Concerns\WithIdempotentMutations;
use App\Models\Period\BankAccount;
use App\Models\Period\CashAccount;
use App\Models\Period\Document;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class PaymentForm extends Component
{
    use WithFinancialRecordCorrection;
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

    protected function restoreFinancialCurrency(Document $document): void
    {
        $this->currency = (string) $document->currency;
        $this->exchangeRate = (string) $document->exchange_rate;
    }

    protected function financialDocumentType(): DocumentType
    {
        return DocumentType::Payment;
    }

    protected function financialPermission(): string
    {
        return 'payments';
    }

    public function mount(): void
    {
        $this->seedMutationKeys(['post', 'reverse']);
        abort_unless(auth()->user()?->can('payments.view'), 403);
        $this->documentDate = now()->toDateString();
        $this->reversalDate = now()->toDateString();
    }

    public function post(PostPayment $action): void
    {
        abort_if($this->correctionMode, 422, 'Önce eski kaydı tersleyerek düzeltme işlemine başlayın.');
        abort_unless(auth()->user()?->can('payments.create'), 403);
        $this->validate([
            'contactId' => ['required', 'integer', 'min:1'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'currency' => ['required', 'string', 'size:3'],
            'exchangeRate' => ['required', 'numeric', 'gt:0'],
            'documentDate' => ['required', 'date'],
            'accountType' => ['required', 'in:cash,bank'],
            'accountId' => ['required', 'integer', 'min:1'],
            'sourceInvoiceId' => ['nullable', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);
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
        $this->financialCorrectionPosted();
    }

    public function reverse(ReverseDocument $action): void
    {
        abort_unless(auth()->user()?->can('payments.cancel'), 403);
        abort_unless($this->postedDocumentId !== null, 422);
        $document = Document::query()
            ->where('document_type', $this->financialDocumentType()->value)
            ->where('status', 'posted')
            ->findOrFail($this->postedDocumentId);
        $reversal = $action->handle(
            $document,
            $this->reversalDate,
            $this->reversalReason,
            $this->mutationKey('reverse'),
        );
        $this->completeMutation('reverse');
        $this->financialCorrectionReversed((int) $document->id);
    }

    public function render(): View
    {
        return view('livewire.purchases.payment-form', [
            'contacts' => $this->financialContactOptions(),
            ...$this->financialHistoryData(),
            'cashAccounts' => CashAccount::query()->where('is_active', true)->orderBy('name')->get(),
            'bankAccounts' => BankAccount::query()->where('is_active', true)->orderBy('bank_name')->get(),
            'invoices' => Document::query()
                ->where('document_type', DocumentType::SupplierInvoice->value)
                ->when($this->contactId, fn ($q) => $q->where('contact_id', $this->contactId))
                ->where('currency', $this->currency)
                ->where('status', 'posted')
                ->orderByDesc('document_date')
                ->limit(500)
                ->get(),
        ])->layout('layouts.app', ['pageTitle' => 'Ödeme']);
    }
}

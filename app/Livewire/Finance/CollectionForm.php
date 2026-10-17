<?php

namespace App\Livewire\Finance;

use App\Livewire\Concerns\WithDocumentEditDeepLink;
use App\Actions\Documents\ReverseDocument;
use App\Actions\Finance\PostCollection;
use App\Enums\DocumentType;
use App\Livewire\Concerns\WithFinancialRecordCorrection;
use App\Livewire\Concerns\WithIdempotentMutations;
use App\Models\Period\BankAccount;
use App\Models\Period\CashAccount;
use App\Models\Period\Document;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class CollectionForm extends Component
{
    use WithFinancialRecordCorrection;
    use WithIdempotentMutations;
    use WithDocumentEditDeepLink;

    public ?int $contactId = null;

    public string $amount = '0.0000';

    public string $accountType = 'cash';

    public ?int $accountId = null;

    public string $documentDate = '';

    public ?int $sourceInvoiceId = null;

    public string $note = '';

    public string $reference = '';

    public ?int $postedDocumentId = null;

    public string $reversalDate = '';

    public string $reversalReason = '';

    protected function financialDocumentType(): DocumentType
    {
        return DocumentType::Collection;
    }

    protected function financialPermission(): string
    {
        return 'collections';
    }


    private function selectLinkedCorrectionDocument(): void
    {
        $id = $this->linkedDocumentId();
        if ($id !== null) {
            $this->selectForCorrection($id);
        }
    }

    public function mount(): void
    {
        $this->seedMutationKeys(['post', 'reverse']);
        abort_unless(auth()->user()?->can('collections.view'), 403);
        $this->documentDate = now()->toDateString();
        $this->reversalDate = now()->toDateString();
        $this->selectLinkedCorrectionDocument();
    }

    public function post(PostCollection $action): void
    {
        abort_if($this->correctionMode, 422, 'Önce eski kaydı tersleyerek düzeltme işlemine başlayın.');
        abort_unless(auth()->user()?->can('collections.create'), 403);
        $this->validate([
            'contactId' => ['required', 'integer', 'min:1'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'documentDate' => ['required', 'date'],
            'accountType' => ['required', 'in:cash,bank'],
            'accountId' => ['required', 'integer', 'min:1'],
            'sourceInvoiceId' => ['nullable', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:1000'],
            'reference' => ['nullable', 'string', 'max:140'],
        ]);
        $document = $action->handle(
            (int) $this->contactId,
            $this->amount,
            $this->documentDate,
            $this->accountType,
            (int) $this->accountId,
            $this->mutationKey('post'),
            $this->sourceInvoiceId,
            $this->note !== '' ? $this->note : null,
            $this->reference !== '' ? $this->reference : null,
        );
        $this->completeMutation('post');
        $this->postedDocumentId = (int) $document->id;
        $this->financialCorrectionPosted();
    }

    public function reverse(ReverseDocument $action): void
    {
        abort_unless(auth()->user()?->can('collections.cancel'), 403);
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
        return view('livewire.finance.collection-form', [
            'contacts' => $this->financialContactOptions(),
            ...$this->financialHistoryData(),
            'cashAccounts' => CashAccount::query()->where('is_active', true)->orderBy('name')->get(),
            'bankAccounts' => BankAccount::query()->where('is_active', true)->orderBy('bank_name')->get(),
            'invoices' => Document::query()
                ->where('document_type', 'sales_invoice')
                ->when($this->contactId, fn ($q) => $q->where('contact_id', $this->contactId))
                ->where('status', 'posted')
                ->orderByDesc('document_date')
                ->limit(500)
                ->get(),
        ])->layout('layouts.app', ['pageTitle' => 'Tahsilat']);
    }
}

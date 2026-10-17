<?php

namespace App\Livewire\Finance;

use App\Actions\Finance\PostContactDebitCredit;
use App\Actions\Documents\ReverseDocument;
use App\Enums\DocumentType;
use App\Models\Period\Document;
use App\Models\Period\DocumentRelation;
use App\Models\Period\ContactTransaction;
use App\Livewire\Concerns\WithIdempotentMutations;
use App\Models\Period\Contact;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
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

    #[Locked]
    public ?int $correctionSourceId = null;

    #[Locked]
    public bool $correctionReady = false;

    public string $reversalDate = '';

    public string $reversalReason = '';

    public function mount(): void
    {
        $this->seedMutationKeys(['post', 'reverse']);
        abort_unless(auth()->user()?->can('contacts.update'), 403);
        $this->documentDate = now()->toDateString();
        $this->reversalDate = now()->toDateString();
    }

    public function post(PostContactDebitCredit $action): void
    {
        abort_unless(auth()->user()?->can('contacts.update'), 403);
        abort_if($this->correctionSourceId !== null && ! $this->correctionReady, 409,
            'Düzeltme için önce eski cari fişin ters kaydı onaylanmalıdır.');
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
        $this->correctionSourceId = null;
        $this->correctionReady = false;
    }

    public function prepareCorrection(int $id): void
    {
        abort_unless(auth()->user()?->can('contacts.update'), 403);

        $document = Document::query()
            ->where('document_type', DocumentType::ContactDebitCredit->value)
            ->where('status', 'posted')->findOrFail($id);

        abort_if(DocumentRelation::query()->where('relation_type', 'reversal_of')
            ->where(fn ($query) => $query->where('target_document_id', $id)
                ->orWhere('source_document_id', $id))->exists(),
            422, 'Bu cari fişi zaten terslenmiş veya ters kayıt fişidir.');

        $movement = ContactTransaction::query()
            ->where('document_id', $id)->whereNull('reversal_of_id')->firstOrFail();

        $this->contactId = (int) $document->contact_id;
        $this->direction = $movement->direction;
        $this->amount = (string) $document->grand_total;
        $this->documentDate = $document->document_date->toDateString();
        $this->reason = (string) ($movement->description ?: 'Cari fiş düzeltmesi');
        $this->note = (string) ($document->notes ?? '');
        $this->correctionSourceId = $id;
        $this->correctionReady = false;
        $this->reversalDate = now()->toDateString();
        $this->reversalReason = '';
    }

    public function cancelCorrection(): void
    {
        $this->correctionSourceId = null;
        $this->correctionReady = false;
    }

    public function reverseForCorrection(ReverseDocument $action): void
    {
        abort_unless(auth()->user()?->can('contacts.update') && $this->correctionSourceId !== null
            && ! $this->correctionReady, 403);

        $this->validate([
            'reversalDate' => ['required', 'date'],
            'reversalReason' => ['required', 'string', 'min:3', 'max:1000'],
        ]);

        $document = Document::query()
            ->where('document_type', DocumentType::ContactDebitCredit->value)
            ->where('status', 'posted')->findOrFail($this->correctionSourceId);
        $action->handle($document, $this->reversalDate, $this->reversalReason,
            $this->mutationKey('reverse'));
        $this->completeMutation('reverse');
        $this->correctionReady = true;
    }

    public function render(): View
    {
        return view('livewire.finance.contact-debit-credit-form', [
            'recentDocuments' => Document::query()
                ->where('document_type', DocumentType::ContactDebitCredit->value)
                ->where('status', 'posted')
                ->whereDoesntHave('outgoingRelations', fn ($q) => $q
                    ->where('relation_type', 'reversal_of'))
                ->whereDoesntHave('incomingRelations', fn ($q) => $q
                    ->where('relation_type', 'reversal_of'))
                ->with(['contact', 'contactTransactions'])
                ->orderByDesc('document_date')->orderByDesc('id')->limit(50)->get(),
            'contacts' => Contact::query()->where('is_active', true)->orderBy('title')->limit(500)->get(),
        ])->layout('layouts.app', ['pageTitle' => 'Cari Borç / Alacak Fişi']);
    }
}

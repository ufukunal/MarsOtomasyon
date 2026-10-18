<?php

namespace App\Livewire\Finance;

use App\Livewire\Concerns\WithDocumentEditDeepLink;
use App\Actions\Finance\PostContactDebitCredit;
use App\Actions\Documents\ReverseDocument;
use App\Enums\DocumentType;
use App\Models\Period\Document;
use App\Models\Period\DocumentRelation;
use App\Models\Period\ContactTransaction;
use App\Livewire\Concerns\WithIdempotentMutations;
use App\Models\Period\Contact;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Component;

class ContactDebitCreditForm extends Component
{
    use WithIdempotentMutations;
    use WithDocumentEditDeepLink;

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

    #[Locked]
    public ?int $selectedRecordId = null;

    public function openRecord(int $id): void
    {
        abort_unless(auth()->user()?->can('contacts.update'), 403);
        Document::query()->where('document_type', DocumentType::ContactDebitCredit->value)
            ->where('status', 'posted')->findOrFail($id);
        $this->selectedRecordId = $id;
    }

    public function closeRecord(): void
    {
        $this->selectedRecordId = null;
    }

    public string $reversalDate = '';

    public string $reversalReason = '';

    public function mount(): void
    {
        $this->seedMutationKeys(['post', 'reverse']);
        abort_unless(auth()->user()?->can('contacts.update'), 403);
        $this->documentDate = now()->toDateString();
        $this->reversalDate = now()->toDateString();
        $id = $this->linkedDocumentId();
        if ($id !== null) {
            $this->prepareCorrection($id);
        }
    }

    public function post(PostContactDebitCredit $action): void
    {
        abort_unless(auth()->user()?->can('contacts.update'), 403);
        // Correct an existing posted document with one Save; never leave
        // the original reversed when replacement validation/posting fails.
        $document = DB::connection('period')->transaction(function () use ($action) {
            $key = $this->mutationKey('post');
            if ($this->correctionSourceId !== null && ! $this->correctionReady) {
                $source = Document::query()
                    ->where('document_type', DocumentType::ContactDebitCredit->value)
                    ->where('status', 'posted')->lockForUpdate()->findOrFail($this->correctionSourceId);
                app(ReverseDocument::class)->handle(
                    $source, now()->toDateString(),
                    'Cari fişi düzenlendi; eski işlem denetim geçmişinde saklandı.',
                    $this->childMutationKey('post', 'old-revision'),
                );
                $key = $this->childMutationKey('post', 'new-revision');
            }
            return $action->handle(
            (int) $this->contactId,
            $this->direction,
            $this->amount,
            $this->documentDate,
            $this->reason,
            $key,
            $this->note !== '' ? $this->note : null,
            );
        }, attempts: 3);
        $this->completeMutation('post');
        $this->postedDocumentId = (int) $document->id;
        $this->selectedRecordId = (int) $document->id;
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
        $this->selectedRecordId = $id;
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

    /** Remove a posted fiş from active records, retaining ledger and audit history. */
    public function deleteRecord(int $id): void
    {
        abort_unless(auth()->user()?->can('contacts.update'), 403);
        $source = Document::query()->where('document_type', DocumentType::ContactDebitCredit->value)
            ->where('status', 'posted')->findOrFail($id);
        app(ReverseDocument::class)->handle(
            $source, now()->toDateString(),
            'Cari fişi kullanıcı tarafından silindi; mali geçmiş korundu.',
            $this->childMutationKey('reverse', 'delete:'.$id),
        );
        $this->completeMutation('reverse');
        if ($this->selectedRecordId === $id) {
            $this->selectedRecordId = null;
        }
        if ($this->correctionSourceId === $id || $this->postedDocumentId === $id) {
            $this->cancelCorrection();
            $this->postedDocumentId = null;
        }
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
            'selectedRecord' => $this->selectedRecordId === null ? null : Document::query()
                ->where('document_type', DocumentType::ContactDebitCredit->value)
                ->where('status', 'posted')->with('contact')->find($this->selectedRecordId),
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

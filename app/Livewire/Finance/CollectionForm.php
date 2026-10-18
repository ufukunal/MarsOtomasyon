<?php

namespace App\Livewire\Finance;

use App\Livewire\Concerns\WithDocumentEditDeepLink;
use App\Actions\Documents\ReverseDocument;
use App\Actions\Finance\PostCollection;
use App\Enums\DocumentType;
use App\Livewire\Concerns\WithFinancialRecordCorrection;
use App\Livewire\Concerns\WithIdempotentMutations;
use App\Livewire\Concerns\WithVerifiedRecordDeletion;
use App\Models\Period\BankAccount;
use App\Models\Period\CashAccount;
use App\Models\Period\Document;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class CollectionForm extends Component
{
    /** @return list<string> */
    protected function allowedRecordDeletionActions(): array
    {
        return ['deleteRecord'];
    }

    use WithFinancialRecordCorrection;
    use WithIdempotentMutations;
    use WithVerifiedRecordDeletion;
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
        // One Save performs the audit reversal and replacement atomically.
        // A failed replacement rolls the original ledger back unchanged.
        $document = DB::connection('period')->transaction(function () use ($action) {
            $key = $this->mutationKey('post');
            if ($this->correctionMode) {
                abort_unless(auth()->user()?->can('collections.cancel'), 403);
                $source = Document::query()->where('document_type', DocumentType::Collection->value)
                    ->where('status', 'posted')->lockForUpdate()
                    ->findOrFail($this->postedDocumentId);
                app(ReverseDocument::class)->handle(
                    $source,
                    now()->toDateString(),
                    'Tahsilat düzenlendi; eski işlem denetim geçmişinde saklandı.',
                    $this->childMutationKey('post', 'old-revision'),
                );
                $key = $this->childMutationKey('post', 'new-revision');
            }

            return $action->handle(
            (int) $this->contactId,
            $this->amount,
            $this->documentDate,
            $this->accountType,
            (int) $this->accountId,
            $key,
            $this->sourceInvoiceId,
            $this->note !== '' ? $this->note : null,
            $this->reference !== '' ? $this->reference : null,
            );
        }, attempts: 3);
        $this->completeMutation('post');
        $this->postedDocumentId = (int) $document->id;
        $this->financialCorrectionPosted();
    }

    /** Immediate audited removal from the active list; no reversal form or second confirmation. */
    public function deleteRecord(int $id): void
    {
        $this->requireVerifiedRecordDeletion('deleteRecord');
        abort_unless(auth()->user()?->can('collections.cancel'), 403);
        $document = Document::query()
            ->where('document_type', DocumentType::Collection->value)
            ->where('status', 'posted')->findOrFail($id);
        app(ReverseDocument::class)->handle(
            $document,
            now()->toDateString(),
            'Tahsilat kullanıcı tarafından silindi; mali geçmiş korundu.',
            $this->childMutationKey('reverse', 'delete:'.$id),
        );
        $this->completeMutation('reverse');
        if ($this->postedDocumentId === $id) {
            $this->postedDocumentId = null;
            $this->selectedFinancialRecordId = null;
            $this->correctionMode = false;
            $this->correctionSourceId = null;
        }
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

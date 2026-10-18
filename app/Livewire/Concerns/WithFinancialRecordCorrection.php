<?php

namespace App\Livewire\Concerns;

use App\Enums\DocumentType;
use App\Models\Period\BankMovement;
use App\Models\Period\CashMovement;
use App\Models\Period\Contact;
use App\Models\Period\Document;
use App\Models\Period\DocumentRelation;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Locked;

/**
 * Financial corrections retain the posted ledger, require reversal, and only
 * then allow a replacement posting. No posted amount is edited in place.
 */
trait WithFinancialRecordCorrection
{
    public string $contactSearch = '';

    public bool $correctionMode = false;

    public ?int $correctionSourceId = null;

    #[Locked]
    public ?int $selectedFinancialRecordId = null;

    public function openFinancialRecord(int $id): void
    {
        abort_unless(auth()->user()?->can($this->financialPermission().'.view'), 403);
        Document::query()
            ->where('document_type', $this->financialDocumentType()->value)
            ->where('status', 'posted')->findOrFail($id);
        $this->selectedFinancialRecordId = $id;
    }

    public function closeFinancialRecord(): void
    {
        $this->selectedFinancialRecordId = null;
    }

    public function selectedFinancialRecord(): ?Document
    {
        if ($this->selectedFinancialRecordId === null
            || ! auth()->user()?->can($this->financialPermission().'.view')) {
            return null;
        }

        return Document::query()
            ->where('document_type', $this->financialDocumentType()->value)
            ->where('status', 'posted')->find($this->selectedFinancialRecordId);
    }

    public function selectedFinancialRecordIsReversed(): bool
    {
        if ($this->selectedFinancialRecordId === null) {
            return false;
        }
        return DocumentRelation::query()->where('relation_type', 'reversal_of')
            ->where(fn ($q) => $q
                ->where('source_document_id', $this->selectedFinancialRecordId)
                ->orWhere('target_document_id', $this->selectedFinancialRecordId))->exists();
    }

    abstract protected function financialDocumentType(): DocumentType;

    abstract protected function financialPermission(): string;

    /**
     * Collection is always TRY. Payment overrides these hooks, preventing
     * undefined currency/rate properties in the CollectionForm trait context.
     */
    protected function financialCurrency(): string
    {
        return 'TRY';
    }

    protected function financialExchangeRate(): string
    {
        return '1.000000';
    }

    public function updatedAccountType(): void
    {
        $this->accountId = null;
    }

    public function updatedContactId(): void
    {
        $this->sourceInvoiceId = null;
    }

    public function canViewFinancialContactBalance(): bool
    {
        return auth()->check() && auth()->user()->can('contacts.view');
    }

    public function contactBalance(): string
    {
        if (! $this->canViewFinancialContactBalance()) {
            return '0.0000';
        }

        return $this->contactId
            ? (Contact::query()->find($this->contactId)?->balance() ?? '0.0000')
            : '0.0000';
    }

    /**
     * TRY ledger preview: debit minus credit. This is only a preview; the
     * authoritative balance is checked inside the posting transaction.
     */
    public function projectedFinancialContactBalance(): string
    {
        $before = $this->contactBalance();
        if (! $this->canViewFinancialContactBalance()) {
            return '0.0000';
        }
        if (! preg_match('/^\\d{1,14}(?:\\.\\d{1,4})?$/D', (string) $this->amount)
            || bccomp((string) $this->amount, '0', 4) <= 0) {
            return $before;
        }
        $amount = (string) $this->amount;
        if ($this->financialDocumentType() === DocumentType::Payment) {
            $linkedInvoice = $this->sourceInvoiceId
                ? Document::query()->where('document_type', DocumentType::SupplierInvoice->value)
                    ->where('contact_id', $this->contactId)->where('status', 'posted')
                    ->find($this->sourceInvoiceId)
                : null;
            // PostPayment uses the linked invoice's rate, not the editable draft rate.
            $rate = (string) ($linkedInvoice?->exchange_rate ?? $this->financialExchangeRate());
            if (! preg_match('/^\\d{1,12}(?:\\.\\d{1,6})?$/D', $rate)
                || bccomp($rate, '0', 6) <= 0) {
                return $before;
            }
            $amount = bcadd(bcmul($amount, $rate, 8), '0', 4);
            return bcadd($before, $amount, 4);
        }
        return bcsub($before, $amount, 4);
    }

    public function canViewFinancialAccountBalance(): bool
    {
        $permission = $this->accountType === 'cash' ? 'cash_accounts.view' : 'bank_accounts.view';

        return auth()->check() && auth()->user()->can($permission);
    }

    public function selectedFinancialAccount(): \App\Models\Period\CashAccount|\App\Models\Period\BankAccount|null
    {
        // This public Livewire method must never reveal account data to users
        // who can post a collection/payment but lack permission to view balances.
        if (! $this->canViewFinancialAccountBalance()) {
            return null;
        }
        if (! $this->accountId) {
            return null;
        }
        return $this->accountType === 'cash'
            ? \App\Models\Period\CashAccount::query()->where('is_active', true)->find($this->accountId)
            : \App\Models\Period\BankAccount::query()->where('is_active', true)->find($this->accountId);
    }

    public function financialAccountBalance(): string
    {
        $account = $this->selectedFinancialAccount();
        if (! $account) {
            return '0.0000';
        }
        return $account instanceof \App\Models\Period\CashAccount
            ? $account->balance() : $account->bookBalance();
    }

    public function financialAccountCurrency(): string
    {
        return (string) ($this->selectedFinancialAccount()?->currency ?? $this->financialCurrency());
    }

    public function financialAccountCurrencyMatches(): bool
    {
        $account = $this->selectedFinancialAccount();
        return $account === null || $account->currency === (string) $this->financialCurrency();
    }

    public function projectedFinancialAccountBalance(): ?string
    {
        if ($this->selectedFinancialAccount() === null || ! $this->financialAccountCurrencyMatches()) {
            return null;
        }
        $before = $this->financialAccountBalance();
        if (! preg_match('/^\\d{1,14}(?:\\.\\d{1,4})?$/D', (string) $this->amount)
            || bccomp((string) $this->amount, '0', 4) <= 0) {
            return $before;
        }
        return $this->financialDocumentType() === DocumentType::Payment
            ? bcsub($before, (string) $this->amount, 4)
            : bcadd($before, (string) $this->amount, 4);
    }

    /** @return Collection<int,Contact> */
    public function financialContactOptions(): Collection
    {
        $query = Contact::query()->where('is_active', true);
        $needle = trim(mb_substr($this->contactSearch, 0, 80));
        if ($needle !== '') {
            $pattern = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $needle).'%';
            $query->where(fn ($q) => $q->where('code', 'ilike', $pattern)
                ->orWhere('title', 'ilike', $pattern));
        }
        $options = $query->orderBy('title')->limit(35)->get();
        if ($this->contactId && ! $options->contains('id', $this->contactId)) {
            $selected = Contact::query()->find($this->contactId);
            if ($selected) {
                $options->prepend($selected);
            }
        }

        return $options;
    }

    public function selectForCorrection(int $id): void
    {
        abort_unless(auth()->check()
            && auth()->user()->can($this->financialPermission().'.view')
            && auth()->user()->can($this->financialPermission().'.cancel'), 403);

        $document = Document::query()
            ->where('document_type', $this->financialDocumentType()->value)
            ->where('status', 'posted')
            ->findOrFail($id);
        abort_if(DocumentRelation::query()->where('target_document_id', $id)
            ->where('relation_type', 'reversal_of')->exists(), 422, 'Belge daha önce terslenmiş.');

        $cash = CashMovement::query()->where('document_id', $id)->whereNull('reversal_of_id')->first();
        $bank = BankMovement::query()->where('document_id', $id)->whereNull('reversal_of_id')->first();
        abort_if(! $cash && ! $bank, 422, 'Belgeye bağlı mali hareket bulunamadı.');

        $this->contactId = (int) $document->contact_id;
        $this->sourceInvoiceId = DocumentRelation::query()->where('source_document_id', $id)
            ->where('relation_type', $this->financialDocumentType() === DocumentType::Collection
                ? 'collection_source' : 'payment_source')->value('target_document_id');
        $this->amount = (string) $document->grand_total;
        $this->documentDate = $document->document_date->toDateString();
        $this->note = (string) ($document->notes ?? '');
        $this->reference = (string) (($document->ui_meta ?? [])['receipt_reference'] ?? '');
        $this->accountType = $cash ? 'cash' : 'bank';
        $this->accountId = $cash ? $cash->cash_account_id : $bank->bank_account_id;
        $this->restoreFinancialCurrency($document);
        $this->postedDocumentId = $id;
        $this->selectedFinancialRecordId = $id;
        $this->correctionMode = true;
        $this->correctionSourceId = null;
        $this->reversalDate = now()->toDateString();
        $this->reversalReason = '';
    }

    protected function restoreFinancialCurrency(Document $document): void
    {
        // Collection documents use TRY; payment overrides this hook.
    }

    public function financialCorrectionPosted(): void
    {
        $this->selectedFinancialRecordId = $this->postedDocumentId;
        $this->correctionSourceId = null;
        $this->correctionMode = false;
    }

    public function financialCorrectionReversed(int $id): void
    {
        $this->correctionSourceId = $this->correctionMode ? $id : null;
        $this->correctionMode = false;
        $this->postedDocumentId = null;
        $this->reversalReason = '';
    }

    /** @return array<string,mixed> */
    public function financialHistoryData(): array
    {
        $recent = Document::query()->where('document_type', $this->financialDocumentType()->value)
            ->where('status', 'posted')
            ->when($this->contactId, fn ($q) => $q->where('contact_id', $this->contactId))
            ->orderByDesc('id')->limit(35)->get();

        $reversed = DocumentRelation::query()->where('relation_type', 'reversal_of')
            ->whereIn('target_document_id', $recent->pluck('id'))
            ->pluck('target_document_id')->map(fn ($id): int => (int) $id)->all();

        return ['recentRecords' => $recent, 'reversedIds' => $reversed];
    }
}

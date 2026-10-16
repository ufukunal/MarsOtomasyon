<?php

namespace App\Livewire\Concerns;

use App\Enums\DocumentType;
use App\Models\Period\BankMovement;
use App\Models\Period\CashMovement;
use App\Models\Period\Contact;
use App\Models\Period\Document;
use App\Models\Period\DocumentRelation;
use Illuminate\Database\Eloquent\Collection;

/**
 * Financial corrections retain the posted ledger, require reversal, and only
 * then allow a replacement posting. No posted amount is edited in place.
 */
trait WithFinancialRecordCorrection
{
    public string $contactSearch = '';

    public bool $correctionMode = false;

    public ?int $correctionSourceId = null;

    abstract protected function financialDocumentType(): DocumentType;

    abstract protected function financialPermission(): string;

    public function updatedAccountType(): void
    {
        $this->accountId = null;
    }

    public function updatedContactId(): void
    {
        $this->sourceInvoiceId = null;
    }

    public function contactBalance(): string
    {
        return $this->contactId
            ? (Contact::query()->find($this->contactId)?->balance() ?? '0.0000')
            : '0.0000';
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
        abort_unless(auth()->user()?->can($this->financialPermission().'.view')
            && auth()->user()?->can($this->financialPermission().'.cancel'), 403);

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
        $this->accountType = $cash ? 'cash' : 'bank';
        $this->accountId = $cash ? $cash->cash_account_id : $bank?->bank_account_id;
        if (property_exists($this, 'currency')) {
            $this->currency = (string) $document->currency;
            $this->exchangeRate = (string) $document->exchange_rate;
        }
        $this->postedDocumentId = $id;
        $this->correctionMode = true;
        $this->correctionSourceId = null;
        $this->reversalDate = now()->toDateString();
        $this->reversalReason = '';
    }

    public function financialCorrectionPosted(): void
    {
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

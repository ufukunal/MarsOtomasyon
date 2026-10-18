<?php

namespace App\Livewire\Finance;

use App\Actions\Finance\PostContactTransfer;
use App\Actions\Finance\ReverseContactTransfer;
use App\Livewire\Concerns\WithIdempotentMutations;
use App\Livewire\Concerns\WithVerifiedRecordDeletion;
use App\Models\Period\Contact;
use App\Models\Period\ContactTransfer;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

class ContactTransferForm extends Component
{
    /** @return list<string> */
    protected function allowedRecordDeletionActions(): array
    {
        return ['deleteRecord'];
    }

    use WithIdempotentMutations;
    use WithVerifiedRecordDeletion;

    public ?int $sourceContactId = null;

    public ?int $targetContactId = null;

    public string $sourceSearch = '';

    public string $targetSearch = '';

    public string $balanceType = 'debit';

    public string $amount = '';

    public string $documentDate = '';

    public string $reason = '';

    public string $reference = '';

    public bool $showOverdraftConfirm = false;

    public bool $overdraftConfirmed = false;

    public bool $overdraftAcknowledged = false;

    public ?int $postedTransferId = null;

    #[Locked]
    public ?int $selectedRecordId = null;

    public function openRecord(int $id): void
    {
        abort_unless(auth()->user()?->can('contact_transfers.view'), 403);
        ContactTransfer::query()->findOrFail($id);
        $this->selectedRecordId = $id;
    }

    public function closeRecord(): void
    {
        $this->selectedRecordId = null;
    }

    public string $reversalDate = '';

    public string $reversalReason = '';

    #[Locked]
    public ?int $pendingReversalId = null;

    #[Locked]
    public ?int $correctionSourceId = null;

    #[Locked]
    public bool $correctionReady = false;

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('contact_transfers.view'), 403);
        $this->seedMutationKeys(['post', 'reverse']);
        $this->documentDate = now()->toDateString();
        $this->reversalDate = now()->toDateString();
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['sourceContactId', 'targetContactId', 'balanceType', 'amount'], true)) {
            $this->showOverdraftConfirm = false;
            $this->overdraftConfirmed = false;
            $this->overdraftAcknowledged = false;
        }
    }

    /** Clear current fields without touching any posted transfer. */
    public function clearForm(): void
    {
        abort_unless(auth()->user()?->can('contact_transfers.create'), 403);
        $this->correctionSourceId = null;
        $this->correctionReady = false;
        $this->pendingReversalId = null;
        $this->reset(['sourceContactId', 'targetContactId', 'sourceSearch', 'targetSearch',
            'amount', 'reason', 'reference', 'postedTransferId',
            'showOverdraftConfirm', 'overdraftConfirmed', 'overdraftAcknowledged']);
        $this->balanceType = 'debit';
        $this->documentDate = now()->toDateString();
        $this->resetValidation();
    }

    public function sourceContact(): ?Contact
    {
        return $this->sourceContactId ? Contact::query()->find($this->sourceContactId) : null;
    }

    public function targetContact(): ?Contact
    {
        return $this->targetContactId ? Contact::query()->find($this->targetContactId) : null;
    }

    public function canViewContactBalances(): bool
    {
        return auth()->check() && auth()->user()->can('contacts.view');
    }

    private function sourceLedgerBalance(): string
    {
        return $this->sourceContact()?->balance() ?? '0.0000';
    }

    public function sourceBalance(): string
    {
        return $this->canViewContactBalances() ? $this->sourceLedgerBalance() : '0.0000';
    }

    public function targetBalance(): string
    {
        return $this->canViewContactBalances()
            ? ($this->targetContact()?->balance() ?? '0.0000') : '0.0000';
    }

    public function projectedSourceBalance(): string
    {
        $balance = $this->sourceBalance();
        if (! preg_match('/^\d{1,14}(?:\.\d{1,4})?$/D', $this->amount) || bccomp($this->amount, '0', 4) <= 0) {
            return $balance;
        }

        return $this->balanceType === 'debit'
            ? bcsub($balance, $this->amount, 4)
            : bcadd($balance, $this->amount, 4);
    }

    public function projectedTargetBalance(): string
    {
        $balance = $this->targetBalance();
        if (! preg_match('/^\d{1,14}(?:\.\d{1,4})?$/D', $this->amount) || bccomp($this->amount, '0', 4) <= 0) {
            return $balance;
        }

        return $this->balanceType === 'debit'
            ? bcadd($balance, $this->amount, 4)
            : bcsub($balance, $this->amount, 4);
    }

    /** @return Collection<int, Contact> */
    public function contactOptions(string $search, ?int $selectedId): Collection
    {
        $query = Contact::query()->where('is_active', true);
        $needle = mb_substr(trim($search), 0, 80);
        if ($needle !== '') {
            $pattern = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $needle).'%';
            $query->where(fn ($builder) => $builder->where('code', 'ilike', $pattern)
                ->orWhere('title', 'ilike', $pattern));
        }
        $options = $query->orderBy('title')->limit(35)->get();
        if ($selectedId && ! $options->contains('id', $selectedId)) {
            $selected = Contact::query()->find($selectedId);
            if ($selected) {
                $options->prepend($selected);
            }
        }

        return $options;
    }

    public function cancelOverdraft(): void
    {
        $this->showOverdraftConfirm = false;
        $this->overdraftConfirmed = false;
        $this->overdraftAcknowledged = false;
    }

    public function confirmOverdraft(PostContactTransfer $action): void
    {
        if (! $this->overdraftAcknowledged) {
            throw ValidationException::withMessages(['overdraftAcknowledged' => 'Bakiye aşımını açıkça onaylayın.']);
        }
        $this->overdraftConfirmed = true;
        try {
            $this->post($action);
        } finally {
            $this->overdraftConfirmed = false;
            $this->overdraftAcknowledged = false;
        }
    }

    public function post(PostContactTransfer $action): void
    {
        abort_unless(auth()->user()?->can('contact_transfers.create'), 403);
        $this->validate([
            'sourceContactId' => ['required', 'integer', 'different:targetContactId'],
            'targetContactId' => ['required', 'integer'],
            'balanceType' => ['required', 'in:debit,credit'],
            'amount' => ['required', 'regex:/^\d{1,14}(?:\.\d{1,4})?$/D'],
            'documentDate' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'reference' => ['nullable', 'string', 'max:100'],
        ], [], [
            'sourceContactId' => 'Kaynak cari',
            'targetContactId' => 'Hedef cari',
            'amount' => 'Virman tutarı',
        ]);

        $sourceBalance = $this->sourceLedgerBalance();
        if ($this->correctionSourceId !== null && ! $this->correctionReady) {
            // Preview the old transfer's reversal before comparing balance.
            // This is a UX projection; authoritative checks run in PostContactTransfer.
            $old = ContactTransfer::query()->whereNull('reversal_of_id')
                ->findOrFail($this->correctionSourceId);
            $oldAmount = (string) $old->amount;
            if ((int) $old->source_contact_id === (int) $this->sourceContactId) {
                $sourceBalance = $old->balance_type === 'debit'
                    ? bcadd($sourceBalance, $oldAmount, 4)
                    : bcsub($sourceBalance, $oldAmount, 4);
            }
            if ((int) $old->target_contact_id === (int) $this->sourceContactId) {
                $sourceBalance = $old->balance_type === 'debit'
                    ? bcsub($sourceBalance, $oldAmount, 4)
                    : bcadd($sourceBalance, $oldAmount, 4);
            }
        }
        $available = $this->balanceType === 'debit'
            ? (bccomp($sourceBalance, '0', 4) > 0 ? $sourceBalance : '0.0000')
            : (bccomp($sourceBalance, '0', 4) < 0 ? bcmul($sourceBalance, '-1', 4) : '0.0000');

        if (bccomp($this->amount, $available, 4) > 0 && ! $this->overdraftConfirmed) {
            $this->showOverdraftConfirm = true;

            return;
        }

        if ($this->overdraftConfirmed && ! $this->overdraftAcknowledged) {
            throw ValidationException::withMessages(['overdraftAcknowledged' => 'Bakiye aşımını açıkça onaylayın.']);
        }

        if ($this->overdraftConfirmed && trim($this->reason) === '') {
            throw ValidationException::withMessages(['reason' => 'Bakiye aşımı için gerekçe girin.']);
        }

        // Correction is one period transaction: undo old ledger effects and
        // post the edited transfer together, or leave the original untouched.
        $transfer = DB::connection('period')->transaction(function () use ($action) {
            $key = $this->mutationKey('post');
            if ($this->correctionSourceId !== null) {
                if (! $this->correctionReady) {
                    $old = ContactTransfer::query()->whereNull('reversal_of_id')
                        ->lockForUpdate()->findOrFail($this->correctionSourceId);
                    $date = max(now()->toDateString(), $old->transfer_date->toDateString());
                    app(ReverseContactTransfer::class)->handle(
                        $old, $date,
                        'Cari virman düzenlendi; eski bakiye hareketi geçmişte korundu.',
                        $this->childMutationKey('post', 'old-revision'),
                    );
                }
                $key = $this->childMutationKey('post', 'new-revision');
            }

            return $action->handle(
                (int) $this->sourceContactId,
                (int) $this->targetContactId,
                $this->balanceType,
                $this->amount,
                $this->documentDate,
                $this->reason,
                $key,
                $this->overdraftConfirmed,
                $this->reference !== '' ? $this->reference : null,
            );
        }, attempts: 3);
        $this->completeMutation('post');
        $this->postedTransferId = (int) $transfer->id;
        $this->selectedRecordId = (int) $transfer->id;
        $this->correctionSourceId = null;
        $this->correctionReady = false;
        $this->amount = '';
        $this->reason = '';
        $this->reference = '';
        $this->showOverdraftConfirm = false;
        $this->overdraftConfirmed = false;
        $this->overdraftAcknowledged = false;
    }

    public function prepareCorrection(int $id): void
    {
        abort_unless(auth()->user()?->can('contact_transfers.view')
            && auth()->user()->can('contact_transfers.cancel')
            && auth()->user()->can('contact_transfers.create'), 403);

        $transfer = ContactTransfer::query()->whereNull('reversal_of_id')->findOrFail($id);
        abort_if(ContactTransfer::query()->where('reversal_of_id', $id)->exists(),
            422, 'Virman zaten terslenmiş.');

        $this->sourceContactId = $transfer->source_contact_id;
        $this->targetContactId = $transfer->target_contact_id;
        $this->balanceType = $transfer->balance_type;
        $this->amount = (string) $transfer->amount;
        $this->documentDate = $transfer->transfer_date->toDateString();
        $this->reason = (string) ($transfer->reason ?? '');
        $this->reference = (string) ($transfer->reference ?? '');
        $this->sourceSearch = '';
        $this->targetSearch = '';
        $this->selectedRecordId = $id;
        $this->correctionSourceId = $id;
        $this->correctionReady = false;
        $this->overdraftConfirmed = false;
        $this->overdraftAcknowledged = false;
        $this->showOverdraftConfirm = false;
        $this->pendingReversalId = $id;
        $this->reversalDate = now()->toDateString();
        $this->reversalReason = '';
    }

    /** One-click removal from active virman records with an immutable audit trail. */
    public function deleteRecord(int $id): void
    {
        $this->requireVerifiedRecordDeletion('deleteRecord');
        abort_unless(auth()->user()?->can('contact_transfers.cancel'), 403);
        $source = ContactTransfer::query()->whereNull('reversal_of_id')->findOrFail($id);
        $date = max(now()->toDateString(), $source->transfer_date->toDateString());
        app(ReverseContactTransfer::class)->handle(
            $source, $date,
            'Cari virman kullanıcı tarafından silindi; mali geçmiş korundu.',
            $this->childMutationKey('reverse', 'delete:'.$id),
        );
        $this->completeMutation('reverse');
        if ($this->selectedRecordId === $id) {
            $this->selectedRecordId = null;
        }
        if ($this->correctionSourceId === $id) {
            $this->correctionSourceId = null;
            $this->correctionReady = false;
            $this->postedTransferId = null;
            $this->pendingReversalId = null;
        }
    }

    public function prepareReverse(int $id): void
    {
        abort_unless(auth()->user()?->can('contact_transfers.cancel'), 403);
        $transfer = ContactTransfer::query()->findOrFail($id);
        if ($transfer->reversal_of_id !== null
            || ContactTransfer::query()->where('reversal_of_id', $id)->exists()) {
            throw ValidationException::withMessages([
                'reversalReason' => 'Virman daha önce terslenmiş veya ters kayıttır.',
            ]);
        }
        $this->pendingReversalId = $id;
    }

    public function cancelReverse(): void
    {
        if ($this->correctionSourceId === $this->pendingReversalId) {
            $this->correctionSourceId = null;
            $this->correctionReady = false;
        }
        $this->pendingReversalId = null;
        $this->reversalReason = '';
    }

    public function confirmReverse(ReverseContactTransfer $action): void
    {
        abort_unless(auth()->user()?->can('contact_transfers.cancel'), 403);
        abort_unless($this->pendingReversalId !== null, 422);
        $this->validate([
            'reversalDate' => ['required', 'date'],
            'reversalReason' => ['required', 'string', 'min:3', 'max:1000'],
        ]);
        $id = $this->pendingReversalId;
        $action->handle(
            ContactTransfer::query()->findOrFail($id),
            $this->reversalDate,
            $this->reversalReason,
            $this->childMutationKey('reverse', $id),
        );
        $this->completeMutation('reverse');
        if ($this->correctionSourceId === $id) {
            $this->correctionReady = true;
            $this->pendingReversalId = null;
            $this->reversalReason = '';
        } else {
            $this->cancelReverse();
        }
    }

    public function render(): View
    {
        $transfers = ContactTransfer::query()->with(['sourceContact', 'targetContact'])
            ->orderByDesc('transfer_date')->orderByDesc('id')->limit(50)->get();
        $reversedIds = ContactTransfer::query()
            ->whereIn('reversal_of_id', $transfers->pluck('id'))
            ->pluck('reversal_of_id')->map(fn ($id): int => (int) $id)->all();

        return view('livewire.finance.contact-transfer-form', [
            'sourceOptions' => $this->contactOptions($this->sourceSearch, $this->sourceContactId),
            'targetOptions' => $this->contactOptions($this->targetSearch, $this->targetContactId),
            'transfers' => $transfers,
            'selectedTransfer' => $this->selectedRecordId === null ? null : ContactTransfer::query()
                ->with(['sourceContact', 'targetContact'])->find($this->selectedRecordId),
            'reversedIds' => $reversedIds,
            'pendingReversal' => $this->pendingReversalId === null
                ? null
                : ContactTransfer::query()->with(['sourceContact', 'targetContact'])->find($this->pendingReversalId),
        ])->layout('layouts.app', ['pageTitle' => 'Cari Virman']);
    }
}

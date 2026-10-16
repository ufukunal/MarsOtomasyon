<?php

namespace App\Livewire\Finance;

use App\Actions\Finance\PostContactTransfer;
use App\Actions\Finance\ReverseContactTransfer;
use App\Livewire\Concerns\WithIdempotentMutations;
use App\Models\Period\Contact;
use App\Models\Period\ContactTransfer;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class ContactTransferForm extends Component
{
    use WithIdempotentMutations;

    public ?int $sourceContactId = null;
    public ?int $targetContactId = null;
    public string $sourceSearch = '';
    public string $targetSearch = '';
    public string $balanceType = 'debit';
    public string $amount = '';
    public string $documentDate = '';
    public string $reason = '';
    public bool $showOverdraftConfirm = false;
    public bool $overdraftConfirmed = false;
    public bool $overdraftAcknowledged = false;
    public ?int $postedTransferId = null;
    public string $reversalDate = '';
    public string $reversalReason = '';

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

    public function sourceContact(): ?Contact
    {
        return $this->sourceContactId ? Contact::query()->find($this->sourceContactId) : null;
    }

    public function targetContact(): ?Contact
    {
        return $this->targetContactId ? Contact::query()->find($this->targetContactId) : null;
    }

    public function sourceBalance(): string
    {
        return $this->sourceContact()?->balance() ?? '0.0000';
    }

    public function targetBalance(): string
    {
        return $this->targetContact()?->balance() ?? '0.0000';
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
        $this->post($action);
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
        ], [], [
            'sourceContactId' => 'Kaynak cari',
            'targetContactId' => 'Hedef cari',
            'amount' => 'Virman tutarı',
        ]);

        $sourceBalance = $this->sourceBalance();
        $available = $this->balanceType === 'debit'
            ? (bccomp($sourceBalance, '0', 4) > 0 ? $sourceBalance : '0.0000')
            : (bccomp($sourceBalance, '0', 4) < 0 ? bcmul($sourceBalance, '-1', 4) : '0.0000');

        if (bccomp($this->amount, $available, 4) > 0 && ! $this->overdraftConfirmed) {
            $this->showOverdraftConfirm = true;
            return;
        }

        if ($this->overdraftConfirmed && trim($this->reason) === '') {
            throw ValidationException::withMessages(['reason' => 'Bakiye aşımı için gerekçe girin.']);
        }

        $transfer = $action->handle(
            (int) $this->sourceContactId,
            (int) $this->targetContactId,
            $this->balanceType,
            $this->amount,
            $this->documentDate,
            $this->reason,
            $this->mutationKey('post'),
            $this->overdraftConfirmed,
        );
        $this->completeMutation('post');
        $this->postedTransferId = (int) $transfer->id;
        $this->amount = '';
        $this->reason = '';
        $this->showOverdraftConfirm = false;
        $this->overdraftConfirmed = false;
        $this->overdraftAcknowledged = false;
    }

    public function reverse(ReverseContactTransfer $action, int $id): void
    {
        abort_unless(auth()->user()?->can('contact_transfers.cancel'), 403);
        $this->validate([
            'reversalDate' => ['required', 'date'],
            'reversalReason' => ['required', 'string', 'min:3', 'max:1000'],
        ]);
        $action->handle(
            ContactTransfer::query()->findOrFail($id),
            $this->reversalDate,
            $this->reversalReason,
            $this->childMutationKey('reverse', $id),
        );
        $this->completeMutation('reverse');
        $this->reversalReason = '';
    }

    public function render(): View
    {
        return view('livewire.finance.contact-transfer-form', [
            'sourceOptions' => $this->contactOptions($this->sourceSearch, $this->sourceContactId),
            'targetOptions' => $this->contactOptions($this->targetSearch, $this->targetContactId),
            'transfers' => ContactTransfer::query()->with(['sourceContact', 'targetContact'])
                ->orderByDesc('transfer_date')->orderByDesc('id')->limit(50)->get(),
        ])->layout('layouts.app', ['pageTitle' => 'Cari Virman']);
    }
}

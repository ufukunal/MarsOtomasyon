<?php

namespace App\Livewire\Finance;

use App\Actions\Documents\ReverseDocument;
use App\Actions\Finance\PostAdvance;
use App\Actions\Finance\PostExpense;
use App\Actions\Finance\PostFinanceTransfer;
use App\Actions\Finance\PostManualFinanceMovement;
use App\Actions\Finance\ReverseFinanceTransfer;
use App\Actions\Finance\ReverseManualFinanceMovement;
use App\Enums\DocumentType;
use App\Livewire\Concerns\WithIdempotentMutations;
use App\Models\Period\BankAccount;
use App\Models\Period\BankMovement;
use App\Models\Period\CashAccount;
use App\Models\Period\CashMovement;
use App\Models\Period\Contact;
use App\Models\Period\Document;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class FinanceOperationCenter extends Component
{
    use WithIdempotentMutations;

    public string $date = '';

    public string $accountType = 'cash';

    public ?int $accountId = null;

    public string $direction = 'in';

    public string $amount = '0.0000';

    public ?int $contactId = null;

    public string $exchangeRate = '1.000000';

    public string $movementType = 'manual';

    public string $reference = '';

    public string $note = '';

    public string $transferSourceType = 'cash';

    public ?int $transferSourceId = null;

    public string $transferTargetType = 'bank';

    public ?int $transferTargetId = null;

    public string $transferAmount = '0.0000';

    public string $expenseCategory = '';

    public string $expenseNet = '0.0000';

    public string $expenseVatRate = '20.0000';

    public string $expenseCurrency = 'TRY';

    public string $expenseExchangeRate = '1.000000';

    public string $expenseAccountType = 'cash';

    public ?int $expenseAccountId = null;

    public string $advanceOperation = 'give';

    public ?int $advanceContactId = null;

    public string $advanceAmount = '0.0000';

    public string $advanceCurrency = 'TRY';

    public string $advanceExchangeRate = '1.000000';

    public string $advanceAccountType = 'cash';

    public ?int $advanceAccountId = null;

    public string $reversalDate = '';

    public string $reversalReason = '';

    public string $reverseAccountType = 'cash';

    public ?int $reverseMovementId = null;

    public string $reverseTransferGroupKey = '';

    public ?int $reverseDocumentId = null;

    public function mount(): void
    {
        $this->seedMutationKeys([
            'manual',
            'transfer',
            'expense',
            'advance',
            'reverseManual',
            'reverseTransfer',
            'reverseDocument',
        ]);
        abort_unless(
            auth()->user()?->can('finance_movements.view')
            || auth()->user()?->can('finance_transfers.view')
            || auth()->user()?->can('expenses.view')
            || auth()->user()?->can('advances.view'),
            403,
        );
        $this->date = now()->toDateString();
        $this->reversalDate = now()->toDateString();
    }

    public function postManual(PostManualFinanceMovement $action): void
    {
        $action->handle(
            $this->accountType,
            (int) $this->accountId,
            $this->direction,
            $this->amount,
            $this->date,
            $this->mutationKey('manual'),
            $this->contactId,
            $this->exchangeRate,
            $this->movementType,
            $this->reference ?: null,
            $this->note ?: null,
        );
        $this->completeMutation('manual');
    }

    public function postTransfer(PostFinanceTransfer $action): void
    {
        $action->handle(
            $this->transferSourceType,
            (int) $this->transferSourceId,
            $this->transferTargetType,
            (int) $this->transferTargetId,
            $this->transferAmount,
            $this->date,
            $this->mutationKey('transfer'),
            $this->note ?: null,
        );
        $this->completeMutation('transfer');
    }

    public function postExpense(PostExpense $action): void
    {
        $action->handle(
            $this->expenseCategory,
            $this->expenseNet,
            $this->expenseVatRate,
            $this->date,
            $this->expenseAccountType,
            (int) $this->expenseAccountId,
            $this->expenseCurrency,
            $this->expenseExchangeRate,
            $this->mutationKey('expense'),
            $this->note ?: null,
        );
        $this->completeMutation('expense');
    }

    public function postAdvance(PostAdvance $action): void
    {
        $action->handle(
            $this->advanceOperation,
            (int) $this->advanceContactId,
            $this->advanceAmount,
            $this->date,
            $this->advanceAccountType,
            (int) $this->advanceAccountId,
            $this->advanceCurrency,
            $this->advanceExchangeRate,
            $this->mutationKey('advance'),
            $this->note ?: null,
        );
        $this->completeMutation('advance');
    }

    public function reverseManual(ReverseManualFinanceMovement $action): void
    {
        $action->handle(
            $this->reverseAccountType,
            (int) $this->reverseMovementId,
            $this->reversalDate,
            $this->reversalReason,
            $this->mutationKey('reverseManual'),
        );
        $this->completeMutation('reverseManual');
        $this->reverseMovementId = null;
    }

    public function reverseTransfer(ReverseFinanceTransfer $action): void
    {
        $action->handle(
            $this->reverseTransferGroupKey,
            $this->reversalDate,
            $this->reversalReason,
            $this->mutationKey('reverseTransfer'),
        );
        $this->completeMutation('reverseTransfer');
        $this->reverseTransferGroupKey = '';
    }

    public function reverseDocument(ReverseDocument $action): void
    {
        $document = Document::query()->findOrFail((int) $this->reverseDocumentId);

        $action->handle(
            $document,
            $this->reversalDate,
            $this->reversalReason,
            $this->mutationKey('reverseDocument'),
        );
        $this->completeMutation('reverseDocument');
        $this->reverseDocumentId = null;
    }

    public function render(): View
    {
        return view('livewire.finance.finance-operation-center', [
            'cashAccounts' => CashAccount::query()->where('is_active', true)->orderBy('code')->get(),
            'bankAccounts' => BankAccount::query()->where('is_active', true)->orderBy('code')->get(),
            'contacts' => Contact::query()->where('is_active', true)->orderBy('title')->limit(500)->get(),
            'cashMovements' => CashMovement::query()->latest('movement_date')->latest('id')->limit(50)->get(),
            'bankMovements' => BankMovement::query()
                ->where('origin', 'book')
                ->latest('movement_date')
                ->latest('id')
                ->limit(50)
                ->get(),
            'financeDocuments' => Document::query()
                ->where('status', 'posted')
                ->whereIn('document_type', [
                    DocumentType::Expense->value,
                    DocumentType::Advance->value,
                    DocumentType::AdvanceReturn->value,
                ])
                ->orderByDesc('document_date')
                ->orderByDesc('id')
                ->limit(100)
                ->get(),
        ])->layout('layouts.app', ['pageTitle' => 'Finans İşlemleri']);
    }
}

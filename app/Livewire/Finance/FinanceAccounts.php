<?php

namespace App\Livewire\Finance;

use App\Actions\Finance\SaveBankAccount;
use App\Actions\Finance\SaveCashAccount;
use App\Livewire\Concerns\WithIdempotentMutations;
use App\Models\Period\BankAccount;
use App\Models\Period\CashAccount;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class FinanceAccounts extends Component
{
    use WithIdempotentMutations;

    public string $cashCode = '';

    public string $cashName = '';

    public string $cashCurrency = 'TRY';

    public ?int $cashEditId = null;

    public ?int $cashEditVersion = null;

    public string $bankCode = '';

    public string $bankName = '';

    public string $bankAccountName = '';

    public string $bankIban = '';

    public string $bankCurrency = 'TRY';

    public ?int $bankEditId = null;

    public ?int $bankEditVersion = null;

    public function mount(): void
    {
        $this->seedMutationKeys(['cash', 'bank', 'toggleCash', 'toggleBank']);
        abort_unless(
            auth()->user()?->can('cash_accounts.view') || auth()->user()?->can('bank_accounts.view'),
            403,
        );
    }

    public function saveCash(SaveCashAccount $action): void
    {
        $account = $this->cashEditId === null
            ? null
            : CashAccount::query()->findOrFail($this->cashEditId);

        $this->runPeriodMutation('cash', fn () => $action->handle([
            'code' => $this->cashCode,
            'name' => $this->cashName,
            'currency' => $this->cashCurrency,
            'is_active' => $account->is_active ?? true,
        ], $account, $this->cashEditVersion));

        $this->resetCashForm();
    }

    public function editCash(int $id): void
    {
        abort_unless(auth()->user()?->can('cash_accounts.update'), 403);
        $account = CashAccount::query()->findOrFail($id);
        $this->cashEditId = (int) $account->id;
        $this->cashEditVersion = (int) $account->version;
        $this->cashCode = (string) $account->code;
        $this->cashName = (string) $account->name;
        $this->cashCurrency = (string) $account->currency;
    }

    public function cancelCashEdit(): void
    {
        $this->resetCashForm();
    }

    public function saveBank(SaveBankAccount $action): void
    {
        $account = $this->bankEditId === null
            ? null
            : BankAccount::query()->findOrFail($this->bankEditId);

        $this->runPeriodMutation('bank', fn () => $action->handle([
            'code' => $this->bankCode,
            'bank_name' => $this->bankName,
            'account_name' => $this->bankAccountName,
            'iban' => $this->bankIban,
            'currency' => $this->bankCurrency,
            'is_active' => $account->is_active ?? true,
        ], $account, $this->bankEditVersion));

        $this->resetBankForm();
    }

    public function editBank(int $id): void
    {
        abort_unless(auth()->user()?->can('bank_accounts.update'), 403);
        $account = BankAccount::query()->findOrFail($id);
        $this->bankEditId = (int) $account->id;
        $this->bankEditVersion = (int) $account->version;
        $this->bankCode = (string) $account->code;
        $this->bankName = (string) $account->bank_name;
        $this->bankAccountName = (string) $account->account_name;
        $this->bankIban = (string) ($account->iban ?? '');
        $this->bankCurrency = (string) $account->currency;
    }

    public function cancelBankEdit(): void
    {
        $this->resetBankForm();
    }

    public function toggleCash(int $id, SaveCashAccount $action): void
    {
        $account = CashAccount::query()->findOrFail($id);

        $this->runPeriodMutation('toggleCash', fn () => $action->handle([
            'code' => $account->code,
            'name' => $account->name,
            'currency' => $account->currency,
            'is_active' => ! $account->is_active,
        ], $account, (int) $account->version));
    }

    public function toggleBank(int $id, SaveBankAccount $action): void
    {
        $account = BankAccount::query()->findOrFail($id);

        $this->runPeriodMutation('toggleBank', fn () => $action->handle([
            'code' => $account->code,
            'bank_name' => $account->bank_name,
            'account_name' => $account->account_name,
            'iban' => $account->iban,
            'currency' => $account->currency,
            'is_active' => ! $account->is_active,
        ], $account, (int) $account->version));
    }

    private function resetCashForm(): void
    {
        $this->cashEditId = null;
        $this->cashEditVersion = null;
        $this->cashCode = '';
        $this->cashName = '';
        $this->cashCurrency = 'TRY';
    }

    private function resetBankForm(): void
    {
        $this->bankEditId = null;
        $this->bankEditVersion = null;
        $this->bankCode = '';
        $this->bankName = '';
        $this->bankAccountName = '';
        $this->bankIban = '';
        $this->bankCurrency = 'TRY';
    }

    public function render(): View
    {
        return view('livewire.finance.finance-accounts', [
            'cashAccounts' => CashAccount::query()->orderBy('code')->get(),
            'bankAccounts' => BankAccount::query()->orderBy('code')->get(),
        ])->layout('layouts.app', ['pageTitle' => 'Kasa / Banka Hesapları']);
    }
}

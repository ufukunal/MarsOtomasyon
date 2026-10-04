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

    public string $bankCode = '';

    public string $bankName = '';

    public string $bankAccountName = '';

    public string $bankIban = '';

    public string $bankCurrency = 'TRY';

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
        $this->runPeriodMutation('cash', fn () => $action->handle([
            'code' => $this->cashCode,
            'name' => $this->cashName,
            'currency' => $this->cashCurrency,
            'is_active' => true,
        ]));

        $this->cashCode = '';
        $this->cashName = '';
    }

    public function saveBank(SaveBankAccount $action): void
    {
        $this->runPeriodMutation('bank', fn () => $action->handle([
            'code' => $this->bankCode,
            'bank_name' => $this->bankName,
            'account_name' => $this->bankAccountName,
            'iban' => $this->bankIban,
            'currency' => $this->bankCurrency,
            'is_active' => true,
        ]));

        $this->bankCode = '';
        $this->bankName = '';
        $this->bankAccountName = '';
        $this->bankIban = '';
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

    public function render(): View
    {
        return view('livewire.finance.finance-accounts', [
            'cashAccounts' => CashAccount::query()->orderBy('code')->get(),
            'bankAccounts' => BankAccount::query()->orderBy('code')->get(),
        ])->layout('layouts.app', ['pageTitle' => 'Kasa / Banka Hesapları']);
    }
}

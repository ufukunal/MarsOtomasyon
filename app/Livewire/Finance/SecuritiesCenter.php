<?php

namespace App\Livewire\Finance;

use App\Actions\Finance\CreateSecurity;
use App\Actions\Finance\PostSecurityPayroll;
use App\Livewire\Concerns\WithIdempotentMutations;
use App\Models\Period\BankAccount;
use App\Models\Period\Contact;
use App\Models\Period\Security;
use App\Models\Period\SecurityPayroll;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class SecuritiesCenter extends Component
{
    use WithIdempotentMutations;

    public string $direction = 'incoming';
    public string $kind = 'check';
    public string $instrumentNo = '';
    public ?int $contactId = null;
    public string $amount = '0.0000';
    public string $transactionDate = '';
    public string $dueDate = '';
    public string $bankName = '';
    public ?int $bankAccountId = null;
    public string $note = '';

    /** @var list<int> */
    public array $selectedSecurityIds = [];

    public string $payrollAction = 'endorsement';
    public ?int $payrollContactId = null;
    public ?int $payrollBankAccountId = null;
    public string $payrollDate = '';

    public function mount(): void
    {
        $this->seedMutationKeys(['create', 'payroll']);
        abort_unless(auth()->user()?->can('securities.view'), 403);
        $this->transactionDate = now()->toDateString();
        $this->dueDate = now()->addMonth()->toDateString();
        $this->payrollDate = now()->toDateString();
    }

    public function create(CreateSecurity $action): void
    {
        $action->handle(
            $this->direction,
            $this->kind,
            $this->instrumentNo,
            (int) $this->contactId,
            $this->amount,
            $this->transactionDate,
            $this->dueDate,
            $this->mutationKey('create'),
            $this->bankName ?: null,
            $this->bankAccountId,
            $this->note ?: null,
        );
        $this->completeMutation('create');
        $this->instrumentNo = '';
        $this->amount = '0.0000';
    }

    public function postPayroll(PostSecurityPayroll $action): void
    {
        $action->handle(
            $this->payrollAction,
            $this->selectedSecurityIds,
            $this->payrollDate,
            $this->mutationKey('payroll'),
            $this->payrollContactId,
            $this->payrollBankAccountId,
            $this->note ?: null,
        );
        $this->completeMutation('payroll');
        $this->selectedSecurityIds = [];
    }

    public function render(): View
    {
        return view('livewire.finance.securities-center', [
            'contacts' => Contact::query()->where('is_active', true)->orderBy('title')->limit(500)->get(),
            'bankAccounts' => BankAccount::query()->where('is_active', true)->orderBy('code')->get(),
            'securities' => Security::query()->with(['contact', 'endorsedToContact', 'bankAccount'])
                ->orderBy('due_date')
                ->orderBy('id')
                ->limit(500)
                ->get(),
            'payrolls' => SecurityPayroll::query()->latest('payroll_date')->latest('id')->limit(100)->get(),
        ])->layout('layouts.app', ['pageTitle' => 'Çek / Senet']);
    }
}

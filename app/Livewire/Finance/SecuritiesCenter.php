<?php

namespace App\Livewire\Finance;

use App\Actions\Finance\CreateSecurity;
use App\Actions\Finance\PostSecurityPayroll;
use App\Actions\Finance\ReverseSecurityPayroll;
use App\Actions\Finance\UpdateSecurityDetails;
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

    protected function scopeKind(): ?string
    {
        return null;
    }

    protected function scopeDirection(): ?string
    {
        return null;
    }

    protected function scopeMode(): string
    {
        return 'center';
    }

    protected function scopeTitle(): string
    {
        return 'Çek / Senet';
    }

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

    public ?int $reversePayrollId = null;

    public string $reversalDate = '';

    public string $reversalReason = '';

    public ?int $editingSecurityId = null;

    public int $editingSecurityVersion = 0;

    public string $editingDueDate = '';

    public string $editingNotes = '';

    public function prepareSecurityEdit(int $id): void
    {
        abort_unless(auth()->user()?->can('securities.update'), 403);
        abort_unless(in_array($this->scopeMode(), ['center', 'list', 'create'], true), 403);

        $security = Security::query()->findOrFail($id);
        abort_if($this->scopeKind() !== null && $security->kind !== $this->scopeKind(), 404);
        abort_if(! in_array($security->status, ['portfolio', 'issued'], true)
            || $security->last_payroll_id !== null, 422,
            'İşlem görmüş evrakın mali alanları bu ekranda düzenlenemez.');

        $this->editingSecurityId = $security->id;
        $this->editingSecurityVersion = (int) $security->version;
        $this->editingDueDate = $security->due_date->toDateString();
        $this->editingNotes = (string) ($security->notes ?? '');
    }

    public function cancelSecurityEdit(): void
    {
        $this->editingSecurityId = null;
        $this->editingNotes = '';
    }

    public function saveSecurityEdit(UpdateSecurityDetails $action): void
    {
        abort_unless($this->editingSecurityId !== null, 422);
        $this->validate([
            'editingDueDate' => ['required', 'date'],
            'editingNotes' => ['nullable', 'string', 'max:1000'],
        ]);
        $id = $this->editingSecurityId;
        $action->handle(
            Security::query()->findOrFail($id),
            $this->editingDueDate,
            $this->editingNotes,
            $this->editingSecurityVersion,
            $this->childMutationKey('create', $id),
        );
        $this->completeMutation('create');
        $this->cancelSecurityEdit();
    }

    public function mount(): void
    {
        $this->seedMutationKeys(['create', 'payroll', 'reversePayroll']);
        abort_unless(auth()->user()?->can('securities.view'), 403);
        $this->transactionDate = now()->toDateString();
        $this->dueDate = now()->addMonth()->toDateString();
        $this->payrollDate = now()->toDateString();
        $this->reversalDate = now()->toDateString();
        $this->kind = $this->scopeKind() ?? $this->kind;
        $this->direction = $this->scopeDirection() ?? $this->direction;
        if ($this->scopeMode() === 'endorse') {
            $this->payrollAction = 'endorsement';
        }
    }

    public function create(CreateSecurity $action): void
    {
        abort_unless(in_array($this->scopeMode(), ['center', 'create'], true), 403);
        $action->handle(
            $this->scopeDirection() ?? $this->direction,
            $this->scopeKind() ?? $this->kind,
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
        abort_unless(in_array($this->scopeMode(), ['center', 'endorse'], true), 403);
        if ($this->scopeMode() === 'endorse') {
            $ids = array_values(array_unique(array_map('intval', $this->selectedSecurityIds)));
            $eligible = Security::query()->whereIn('id', $ids)
                ->where('kind', $this->scopeKind())
                ->where('direction', 'incoming')
                ->where('status', 'portfolio')->count();
            abort_if($ids === [] || $eligible !== count($ids), 422);
        }

        $action->handle(
            $this->scopeMode() === 'endorse' ? 'endorsement' : $this->payrollAction,
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

    public function reversePayroll(ReverseSecurityPayroll $action): void
    {
        abort_unless($this->scopeMode() === 'center', 403);
        $payroll = SecurityPayroll::query()->findOrFail((int) $this->reversePayrollId);

        $action->handle(
            $payroll,
            $this->reversalDate,
            $this->reversalReason,
            $this->mutationKey('reversePayroll'),
        );
        $this->completeMutation('reversePayroll');
        $this->reversePayrollId = null;
    }

    public function render(): View
    {
        $securities = Security::query()->with(['contact', 'endorsedToContact', 'bankAccount']);
        if ($this->scopeKind() !== null) {
            $securities->where('kind', $this->scopeKind());
        }
        if ($this->scopeMode() === 'create' && $this->scopeDirection() !== null) {
            $securities->where('direction', $this->scopeDirection());
        }
        if ($this->scopeMode() === 'endorse') {
            $securities->where('direction', 'incoming');
        }

        return view('livewire.finance.securities-center', [
            'screenMode' => $this->scopeMode(),
            'screenTitle' => $this->scopeTitle(),
            'screenKind' => $this->scopeKind(),
            'screenDirection' => $this->scopeDirection(),
            'contacts' => Contact::query()->where('is_active', true)->orderBy('title')->limit(500)->get(),
            'bankAccounts' => BankAccount::query()->where('is_active', true)->orderBy('code')->get(),
            'securities' => $securities->orderBy('due_date')->orderBy('id')->limit(500)->get(),
            'payrolls' => SecurityPayroll::query()->latest('payroll_date')->latest('id')->limit(100)->get(),
        ])->layout('layouts.app', ['pageTitle' => $this->scopeTitle()]);
    }
}

<?php

namespace App\Livewire\Finance;

use App\Actions\Finance\CreateSecurity;
use App\Actions\Finance\PostSecurityPayroll;
use App\Actions\Finance\ReverseSecurityPayroll;
use App\Actions\Finance\UpdateSecurityDetails;
use App\Actions\Finance\ReplaceSecurity;
use App\Livewire\Concerns\WithIdempotentMutations;
use App\Models\Period\BankAccount;
use App\Models\Period\Contact;
use App\Models\Period\Security;
use App\Models\Period\SecurityPayroll;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Locked;

class SecuritiesCenter extends Component
{
    use WithIdempotentMutations;
    use WithPagination;

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

    /** Server-side list filters; applies before pagination. */
    public string $listSearch = '';
    public string $listDirection = '';
    public string $listStatus = '';

    public function updatedListSearch(): void
    {
        $this->resetPage();
    }
    public function updatedListDirection(): void
    {
        $this->resetPage();
    }
    public function updatedListStatus(): void
    {
        $this->resetPage();
    }

    public function canViewSecurityContactBalance(): bool
    {
        return auth()->check() && auth()->user()->can('contacts.view');
    }

    public function selectedContactBalance(): string
    {
        if (! $this->canViewSecurityContactBalance()) {
            return '0.0000';
        }

        return $this->contactId
            ? (Contact::query()->find($this->contactId)?->balance() ?? '0.0000') : '0.0000';
    }

    public function projectedContactBalance(): string
    {
        $before = $this->selectedContactBalance();
        $value = trim($this->amount);
        if (! preg_match('/^\\d{1,14}(?:\\.\\d{1,4})?$/D', $value) || bccomp($value, '0', 4) <= 0) {
            return $before;
        }
        return ($this->scopeDirection() ?? $this->direction) === 'incoming'
            ? bcsub($before, $value, 4) : bcadd($before, $value, 4);
    }

    public function selectedPayrollTotal(): string
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $this->selectedSecurityIds))));
        if ($ids === []) {
            return '0.0000';
        }
        $items = Security::query()->whereIn('id', $ids)
            ->where('status', 'portfolio')->where('direction', 'incoming')
            ->when($this->scopeKind(), fn ($q, $kind) => $q->where('kind', $kind))
            ->get(['id', 'amount']);
        $total = '0.0000';
        foreach ($items as $item) {
            $total = bcadd($total, (string) $item->amount, 4);
        }
        return $total;
    }

    public function payrollTargetBalance(): string
    {
        if (! $this->canViewSecurityContactBalance()) {
            return '0.0000';
        }

        return $this->payrollContactId
            ? (Contact::query()->find($this->payrollContactId)?->balance() ?? '0.0000') : '0.0000';
    }

    public function projectedPayrollTargetBalance(): string
    {
        return bcadd($this->payrollTargetBalance(), $this->selectedPayrollTotal(), 4);
    }

    /** Single-record shortcut while retaining the existing multi-record board. */
    public string $quickEndorseSecurityId = '';

    public function updatedQuickEndorseSecurityId(): void
    {
        $id = (int) $this->quickEndorseSecurityId;
        if ($id <= 0) {
            $this->selectedSecurityIds = [];
            return;
        }
        $eligible = Security::query()->whereKey($id)->where('direction', 'incoming')
            ->where('status', 'portfolio')
            ->when($this->scopeKind(), fn ($q, $kind) => $q->where('kind', $kind))
            ->exists();
        $this->selectedSecurityIds = $eligible ? [$id] : [];
    }

    public string $securityContactSearch = '';
    public string $payrollContactSearch = '';
    public string $correctionContactSearch = '';

    /** Active contacts are queried by name/code, retaining selected IDs. */
    public function securityContactOptions(string $search, ?int $selectedId): \Illuminate\Database\Eloquent\Collection
    {
        $query = Contact::query()->where('is_active', true);
        $needle = mb_substr(trim($search), 0, 80);
        if ($needle !== '') {
            $pattern = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $needle).'%';
            $query->where(fn ($q) => $q->where('code', 'ilike', $pattern)
                ->orWhere('title', 'ilike', $pattern));
        }
        $options = $query->orderBy('title')->limit(35)->get();
        if ($selectedId && ! $options->contains('id', $selectedId)) {
            $selected = Contact::query()->where('is_active', true)->find($selectedId);
            if ($selected) {
                $options->prepend($selected);
            }
        }
        return $options;
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

    #[Locked]
    public ?int $openedSecurityId = null;

    #[Locked]
    public ?int $openedPayrollId = null;

    public function openSecurityRecord(int $id): void
    {
        abort_unless(auth()->user()?->can('securities.view'), 403);
        $security = Security::query()->findOrFail($id);
        abort_if($this->scopeKind() !== null && $security->kind !== $this->scopeKind(), 404);
        abort_if($this->scopeDirection() !== null && $security->direction !== $this->scopeDirection(), 404);
        $this->openedSecurityId = $id;
    }

    public function closeSecurityRecord(): void
    {
        $this->openedSecurityId = null;
    }

    public function openPayrollRecord(int $id): void
    {
        abort_unless($this->scopeMode() === 'center' && auth()->user()?->can('security_payrolls.view'), 403);
        SecurityPayroll::query()->findOrFail($id);
        $this->openedPayrollId = $id;
    }

    public function closePayrollRecord(): void
    {
        $this->openedPayrollId = null;
    }

    public ?int $reversePayrollId = null;

    public string $reversalDate = '';

    public string $reversalReason = '';

    #[Locked]
    public ?int $editingSecurityId = null;

    #[Locked]
    public int $editingSecurityVersion = 0;

    public string $editingDueDate = '';

    public string $editingNotes = '';

    /** Strict financial correction: reverse the old posting, then issue a new record. */
    #[Locked]
    public ?int $financialCorrectionId = null;
    #[Locked]
    public int $financialCorrectionVersion = 0;
    public string $correctedInstrumentNo = '';
    public ?int $correctedContactId = null;
    public string $correctedAmount = '';
    public string $correctedDueDate = '';
    public ?int $correctedBankAccountId = null;
    public string $correctedBankName = '';
    public string $correctedNote = '';
    public string $correctionReason = '';
    public ?int $replacementSecurityId = null;

    public function prepareFinancialCorrection(int $id): void
    {
        abort_unless(auth()->user()->can('securities.update')
            && auth()->user()->can('securities.cancel')
            && auth()->user()->can('securities.create'), 403);
        abort_unless(in_array($this->scopeMode(), ['center', 'list', 'create'], true), 403);
        $security = Security::query()->findOrFail($id);
        abort_if($this->scopeKind() !== null && $security->kind !== $this->scopeKind(), 404);
        abort_if($this->scopeDirection() !== null && $security->direction !== $this->scopeDirection(), 404);
        abort_unless(in_array($security->status, ['portfolio', 'issued'], true)
            && $security->last_payroll_id === null, 422);
        $this->financialCorrectionId = $id;
        $this->correctionContactSearch = '';
        $this->financialCorrectionVersion = (int) $security->version;
        $this->correctedInstrumentNo = (string) $security->instrument_no;
        $this->correctedContactId = (int) $security->contact_id;
        $this->correctedAmount = (string) $security->amount;
        $this->correctedDueDate = $security->due_date->toDateString();
        $this->correctedBankAccountId = $security->bank_account_id;
        $this->correctedBankName = (string) ($security->bank_name ?? '');
        $this->correctedNote = (string) ($security->notes ?? '');
        $this->correctionReason = '';
    }

    public function cancelFinancialCorrection(): void
    {
        $this->financialCorrectionId = null;
        $this->financialCorrectionVersion = 0;
        $this->correctionReason = '';
    }

    public function confirmFinancialCorrection(ReplaceSecurity $action): void
    {
        abort_unless($this->financialCorrectionId !== null, 422);
        $this->validate([
            'correctedInstrumentNo' => ['required', 'string', 'max:80'],
            'correctedContactId' => ['required', 'integer', 'min:1'],
            'correctedAmount' => ['required', 'numeric', 'gt:0'],
            'correctedDueDate' => ['required', 'date'],
            'correctedBankAccountId' => ['nullable', 'integer', 'min:1'],
            'correctedBankName' => ['nullable', 'string', 'max:255'],
            'correctedNote' => ['nullable', 'string', 'max:1000'],
            'correctionReason' => ['required', 'string', 'max:1000'],
        ]);
        $id = $this->financialCorrectionId;
        $old = Security::query()->findOrFail($id);
        abort_if($this->scopeKind() !== null && $old->kind !== $this->scopeKind(), 404);
        abort_if($this->scopeDirection() !== null && $old->direction !== $this->scopeDirection(), 404);
        $new = $action->handle($old, $this->financialCorrectionVersion, [
            'instrument_no' => $this->correctedInstrumentNo,
            'contact_id' => (int) $this->correctedContactId,
            'amount' => $this->correctedAmount,
            'due_date' => $this->correctedDueDate,
            'bank_account_id' => $this->correctedBankAccountId,
            'bank_name' => $this->correctedBankName,
            'notes' => $this->correctedNote,
        ], $this->correctionReason, $this->childMutationKey('replace', $id));
        $this->completeMutation('replace');
        $this->replacementSecurityId = (int) $new->id;
        $this->openedSecurityId = (int) $new->id;
        $this->cancelFinancialCorrection();
    }

    public function prepareSecurityEdit(int $id): void
    {
        abort_unless(auth()->user()->can('securities.update'), 403);
        abort_unless(in_array($this->scopeMode(), ['center', 'list', 'create'], true), 403);

        $security = Security::query()->findOrFail($id);
        abort_if($this->scopeKind() !== null && $security->kind !== $this->scopeKind(), 404);
        abort_if($this->scopeDirection() !== null && $security->direction !== $this->scopeDirection(), 404);
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
        $this->editingSecurityVersion = 0;
        $this->editingDueDate = '';
        $this->editingNotes = '';
    }

    public function saveSecurityEdit(UpdateSecurityDetails $action): void
    {
        abort_unless(auth()->user()->can('securities.update'), 403);
        abort_unless(in_array($this->scopeMode(), ['center', 'list', 'create'], true), 403);
        abort_unless($this->editingSecurityId !== null, 422);
        $security = Security::query()->findOrFail($this->editingSecurityId);
        abort_if($this->scopeKind() !== null && $security->kind !== $this->scopeKind(), 404);
        abort_if($this->scopeDirection() !== null && $security->direction !== $this->scopeDirection(), 404);
        $this->validate([
            'editingDueDate' => ['required', 'date'],
            'editingNotes' => ['nullable', 'string', 'max:1000'],
        ]);
        $id = $this->editingSecurityId;
        $action->handle(
            $security,
            $this->editingDueDate,
            $this->editingNotes,
            $this->editingSecurityVersion,
            $this->childMutationKey('edit', $id),
        );
        $this->completeMutation('edit');
        $this->cancelSecurityEdit();
    }

    public function mount(): void
    {
        $this->seedMutationKeys(['create', 'edit', 'replace', 'payroll', 'reversePayroll']);
        abort_unless(auth()->check() && auth()->user()->can('securities.view'), 403);
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
        $this->validate([
            'instrumentNo' => ['required', 'string', 'max:100'],
            'contactId' => ['required', 'integer', 'min:1'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'transactionDate' => ['required', 'date'],
            'dueDate' => ['required', 'date'],
            'bankName' => ['nullable', 'string', 'max:255'],
            'bankAccountId' => ['nullable', 'integer'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);
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

    /**
     * Select exactly one security for a real payroll action from the list.
     * The posting service revalidates status, permission, currency and bank.
     */
    public function prepareListPayroll(int $id, string $action): void
    {
        abort_unless($this->scopeMode() === 'list', 403);
        abort_unless(auth()->user()->can('security_payrolls.create'), 403);
        $security = Security::query()->findOrFail($id);
        abort_if($this->scopeKind() !== null && $security->kind !== $this->scopeKind(), 404);
        $allowed = match (true) {
            $security->direction === 'incoming' && $security->status === 'portfolio'
                => ['endorsement', 'bank_deposit', 'collection', 'return', 'protest'],
            $security->direction === 'incoming' && $security->status === 'banked'
                => ['collection', 'return', 'protest'],
            $security->direction === 'outgoing' && $security->status === 'issued'
                => ['payment', 'cancel'],
            default => [],
        };
        abort_unless(in_array($action, $allowed, true), 422);
        $this->selectedSecurityIds = [$id];
        $this->payrollAction = $action;
        $this->payrollContactId = null;
        $this->payrollBankAccountId = null;
    }

    public function postPayroll(PostSecurityPayroll $action): void
    {
        abort_unless(in_array($this->scopeMode(), ['center', 'endorse', 'list'], true), 403);
        if ($this->scopeMode() === 'list') {
            $ids = array_values(array_unique(array_map('intval', $this->selectedSecurityIds)));
            abort_unless(count($ids) === 1, 422);
            $instrument = Security::query()->findOrFail($ids[0]);
            abort_if($this->scopeKind() !== null && $instrument->kind !== $this->scopeKind(), 404);
        }
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

    /** Direct audited deletion of a security payroll from active business operations. */
    public function deletePayroll(int $id): void
    {
        abort_unless($this->scopeMode() === 'center', 403);
        abort_unless(auth()->user()?->can('security_payrolls.cancel'), 403);
        $payroll = SecurityPayroll::query()->where('status', 'posted')
            ->where('action', '!=', 'reversal')->findOrFail($id);
        app(ReverseSecurityPayroll::class)->handle(
            $payroll, max(now()->toDateString(), $payroll->payroll_date->toDateString()),
            'Çek/senet bordrosu kullanıcı tarafından silindi; mali geçmiş korundu.',
            $this->childMutationKey('reversePayroll', 'delete:'.$id),
        );
        $this->completeMutation('reversePayroll');
        if ($this->openedPayrollId === $id) {
            $this->openedPayrollId = null;
        }
    }

    public function reversePayroll(ReverseSecurityPayroll $action): void
    {
        abort_unless($this->scopeMode() === 'center', 403);
        abort_unless(auth()->user()->can('security_payrolls.cancel'), 403);
        abort_unless($this->reversePayrollId !== null, 422);
        $this->validate([
            'reversalDate' => ['required', 'date'],
            'reversalReason' => ['required', 'string', 'min:3', 'max:1000'],
        ]);
        $payroll = SecurityPayroll::query()->findOrFail($this->reversePayrollId);

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
        if ($this->scopeDirection() !== null) {
            $securities->where('direction', $this->scopeDirection());
        }
        if ($this->scopeMode() === 'endorse') {
            $securities->where('direction', 'incoming');
        }

        // Scoped KPIs represent all records of this instrument kind, not only the current page.
        $summaryQuery = Security::query()
            ->when($this->scopeKind(), fn ($q, $kind) => $q->where('kind', $kind))
            ->when($this->scopeDirection(), fn ($q, $direction) => $q->where('direction', $direction));
        $summaryRows = $summaryQuery->selectRaw(
            "status, COUNT(*) AS records, COALESCE(SUM(amount), 0)::text AS total_amount"
        )->groupBy('status')->get();
        $summary = ['count' => 0, 'portfolio' => 0, 'issued' => 0, 'banked' => 0, 'endorsed' => 0, 'settled' => 0];
        foreach ($summaryRows as $row) {
            $count = (int) $row->records;
            $summary['count'] += $count;
            if (isset($summary[$row->status])) {
                $summary[$row->status] += $count;
            }
            if (in_array($row->status, ['collected', 'paid'], true)) {
                $summary['settled'] += $count;
            }
        }

        // Search on real records before pagination, never filter only 500 loaded rows.
        $needle = trim(mb_substr($this->listSearch, 0, 80));
        if ($needle !== '') {
            $pattern = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $needle).'%';
            $securities->where(fn ($q) => $q->where('instrument_no', 'ilike', $pattern)
                ->orWhereHas('contact', fn ($c) => $c->where('code', 'ilike', $pattern)
                    ->orWhere('title', 'ilike', $pattern)));
        }
        if ($this->scopeMode() !== 'endorse' && $this->listDirection !== '') {
            $securities->where('direction', $this->listDirection);
        }
        if ($this->scopeMode() !== 'endorse' && $this->listStatus !== '') {
            $securities->where('status', $this->listStatus);
        }

        return view('livewire.finance.securities-center', [
            'screenMode' => $this->scopeMode(),
            'openedSecurity' => $this->openedSecurityId !== null && auth()->user()?->can('securities.view')
                ? Security::query()->with(['contact', 'endorsedToContact', 'bankAccount'])
                    ->when($this->scopeKind(), fn ($q, $kind) => $q->where('kind', $kind))
                    ->when($this->scopeDirection(), fn ($q, $direction) => $q->where('direction', $direction))
                    ->find($this->openedSecurityId) : null,
            'openedPayroll' => $this->scopeMode() === 'center' && $this->openedPayrollId !== null
                && auth()->user()?->can('security_payrolls.view')
                ? SecurityPayroll::query()->find($this->openedPayrollId) : null,
            'screenTitle' => $this->scopeTitle(),
            'screenKind' => $this->scopeKind(),
            'screenDirection' => $this->scopeDirection(),
            'contacts' => $this->securityContactOptions($this->securityContactSearch, $this->contactId),
            'payrollContacts' => $this->securityContactOptions($this->payrollContactSearch, $this->payrollContactId),
            'correctionContacts' => $this->securityContactOptions($this->correctionContactSearch, $this->correctedContactId),
            'bankAccounts' => BankAccount::query()->where('is_active', true)->orderBy('code')->get(),
            'securities' => $securities->orderBy('due_date')->orderBy('id')->paginate(25),
            'securitySummary' => $summary,
            'payrolls' => SecurityPayroll::query()->latest('payroll_date')->latest('id')->limit(100)->get(),
        ])->layout('layouts.app', ['pageTitle' => $this->scopeTitle()]);
    }
}

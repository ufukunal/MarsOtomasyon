<?php

namespace App\Livewire\Finance;

use App\Actions\Finance\ImportBankStatement;
use App\Actions\Finance\ParseBankStatementFile;
use App\Actions\Finance\ReconcileBankStatement;
use App\Livewire\Concerns\WithIdempotentMutations;
use App\Models\Period\BankAccount;
use App\Models\Period\BankMovement;
use App\Models\Period\Contact;
use App\Queries\Finance\SuggestBankStatementMatches;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithFileUploads;

class BankStatementCenter extends Component
{
    use WithFileUploads;
    use WithIdempotentMutations;

    public $file = null;

    public ?int $bankAccountId = null;

    public string $format = 'auto';

    /** @var list<array<string,mixed>> */
    public array $previewRows = [];

    /** @var array<string,mixed>|null */
    public ?array $importResult = null;

    public ?int $selectedStatementId = null;

    public ?int $selectedBookMovementId = null;

    public ?int $selectedContactId = null;

    public string $newMovementType = 'statement_created';

    public function mount(): void
    {
        $this->seedMutationKeys(['import', 'reconcileExisting', 'reconcileCreate']);
        abort_unless(
            auth()->user()?->can('bank_statements.view')
            || auth()->user()?->can('bank_reconciliation.view'),
            403,
        );
    }

    public function preview(ParseBankStatementFile $parser): void
    {
        $this->validate([
            'file' => ['required', 'file', 'max:10240'],
            'bankAccountId' => ['required', 'integer'],
        ]);

        $this->previewRows = $parser->handle(
            $this->file->getRealPath(),
            $this->format,
        );
    }

    public function import(ImportBankStatement $action): void
    {
        $this->validate([
            'file' => ['required', 'file', 'max:10240'],
            'bankAccountId' => ['required', 'integer'],
        ]);

        $this->importResult = $action->handle(
            (int) $this->bankAccountId,
            $this->file->getRealPath(),
            $this->format,
            $this->mutationKey('import'),
        );
        $this->completeMutation('import');
    }

    public function reconcileExisting(ReconcileBankStatement $action): void
    {
        $action->handle(
            (int) $this->selectedStatementId,
            'existing',
            $this->mutationKey('reconcileExisting'),
            $this->selectedBookMovementId,
        );
        $this->completeMutation('reconcileExisting');
        $this->selectedStatementId = null;
        $this->selectedBookMovementId = null;
    }

    public function createFromStatement(ReconcileBankStatement $action): void
    {
        $action->handle(
            (int) $this->selectedStatementId,
            'create',
            $this->mutationKey('reconcileCreate'),
            null,
            $this->selectedContactId,
            $this->newMovementType,
        );
        $this->completeMutation('reconcileCreate');
        $this->selectedStatementId = null;
        $this->selectedContactId = null;
    }

    public function render(SuggestBankStatementMatches $suggestions): View
    {
        return view('livewire.finance.bank-statement-center', [
            'bankAccounts' => BankAccount::query()->where('is_active', true)->orderBy('code')->get(),
            'contacts' => Contact::query()->where('is_active', true)->orderBy('title')->limit(500)->get(),
            'statementRows' => BankMovement::query()
                ->with('account')
                ->where('origin', 'statement')
                ->orderByDesc('movement_date')
                ->orderByDesc('id')
                ->limit(200)
                ->get(),
            'bookRows' => BankMovement::query()
                ->where('origin', 'book')
                ->whereNotIn('id', BankMovement::query()
                    ->where('origin', 'statement')
                    ->whereNotNull('reconciled_movement_id')
                    ->select('reconciled_movement_id'))
                ->orderByDesc('movement_date')
                ->orderByDesc('id')
                ->limit(200)
                ->get(),
            'suggestions' => $this->selectedStatementId
                ? $suggestions->handle($this->selectedStatementId)
                : [],
        ])->layout('layouts.app', ['pageTitle' => 'Banka Ekstresi / Mutabakat']);
    }
}

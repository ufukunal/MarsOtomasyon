<?php

namespace App\Livewire\Finance;

use App\Livewire\Concerns\WithDocumentEditDeepLink;
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
use App\Models\Period\DocumentRelation;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Component;

class FinanceOperationCenter extends Component
{
    use WithIdempotentMutations;
    use WithDocumentEditDeepLink;

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

    #[Locked]
    public string $openedKind = '';

    #[Locked]
    public ?int $openedId = null;

    #[Locked]
    public string $openedAccount = '';

    #[Locked]
    public string $openedGroup = '';

    public function openFinanceDocument(int $id): void
    {
        $document = Document::query()->whereIn('document_type', [
            DocumentType::Expense->value, DocumentType::Advance->value,
            DocumentType::AdvanceReturn->value,
        ])->where('status', 'posted')->findOrFail($id);
        $permission = $document->document_type === DocumentType::Expense ? 'expenses.view' : 'advances.view';
        abort_unless(auth()->user()?->can($permission), 403);
        $this->openedKind = 'document';
        $this->openedId = $id;
    }

    public function openManualRecord(string $account, int $id): void
    {
        abort_unless(auth()->user()?->can('finance_movements.view'), 403);
        abort_unless(in_array($account, ['cash', 'bank'], true), 422);
        $model = $account === 'cash' ? CashMovement::class : BankMovement::class;
        $model::query()->whereNull('document_id')->whereNull('reversal_of_id')->findOrFail($id);
        $this->openedKind = 'manual';
        $this->openedAccount = $account;
        $this->openedId = $id;
    }

    public function openTransferRecord(string $group): void
    {
        abort_unless(auth()->user()?->can('finance_transfers.view'), 403);
        abort_unless($group !== '' && mb_strlen($group) <= 255, 422);
        $exists = CashMovement::query()->where('movement_type', 'transfer')->where('group_key', $group)->exists()
            || BankMovement::query()->where('movement_type', 'transfer')->where('origin', 'book')
                ->where('group_key', $group)->exists();
        abort_unless($exists, 404);
        $this->openedKind = 'transfer';
        $this->openedGroup = $group;
    }

    public function closeOpenedRecord(): void
    {
        $this->openedKind = '';
        $this->openedId = null;
        $this->openedAccount = '';
        $this->openedGroup = '';
    }

    public string $reversalDate = '';

    public string $reversalReason = '';

    public string $reverseAccountType = 'cash';

    public ?int $reverseMovementId = null;

    public string $reverseTransferGroupKey = '';

    public ?int $reverseDocumentId = null;

    #[Locked]
    public ?int $correctionDocumentId = null;

    #[Locked]
    public string $correctionDocumentType = '';

    #[Locked]
    public bool $correctionDocumentReady = false;

    #[Locked]
    public ?int $correctionMovementId = null;

    #[Locked]
    public string $correctionMovementAccountType = '';

    #[Locked]
    public bool $correctionMovementReady = false;

    #[Locked]
    public string $correctionTransferGroup = '';

    #[Locked]
    public bool $correctionTransferReady = false;

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
        $id = $this->linkedDocumentId();
        if ($id !== null) {
            $this->prepareDocumentCorrection($id);
        }
    }

    public function postManual(PostManualFinanceMovement $action): void
    {

        DB::connection('period')->transaction(function () use ($action) {
            if ($this->correctionMovementId !== null && ! $this->correctionMovementReady) {
                app(ReverseManualFinanceMovement::class)->handle(
                    $this->correctionMovementAccountType, $this->correctionMovementId,
                    now()->toDateString(),
                    'Finans hareketi düzenlendi; eski işlem geçmişte korundu.',
                    $this->childMutationKey('manual', 'old-revision'),
                );
            }
            $action->handle(
            $this->accountType,
            (int) $this->accountId,
            $this->direction,
            $this->amount,
            $this->date,
            ($this->correctionMovementId !== null ? $this->childMutationKey('manual', 'new-revision') : $this->mutationKey('manual')),
            $this->contactId,
            $this->exchangeRate,
            $this->movementType,
            $this->reference ?: null,
            $this->note ?: null,
            );
        }, attempts: 3);
        $this->completeMutation('manual');
        $this->correctionMovementId = null;
        $this->correctionMovementReady = false;
    }

    public function postTransfer(PostFinanceTransfer $action): void
    {

        DB::connection('period')->transaction(function () use ($action) {
            if ($this->correctionTransferGroup !== '' && ! $this->correctionTransferReady) {
                app(ReverseFinanceTransfer::class)->handle(
                    $this->correctionTransferGroup, now()->toDateString(),
                    'Finans virmanı düzenlendi; eski hareketler geçmişte korundu.',
                    $this->childMutationKey('transfer', 'old-revision'),
                );
            }
            $action->handle(
            $this->transferSourceType,
            (int) $this->transferSourceId,
            $this->transferTargetType,
            (int) $this->transferTargetId,
            $this->transferAmount,
            $this->date,
            ($this->correctionTransferGroup !== '' ? $this->childMutationKey('transfer', 'new-revision') : $this->mutationKey('transfer')),
            $this->note ?: null,
            );
        }, attempts: 3);
        $this->completeMutation('transfer');
        $this->correctionTransferGroup = '';
        $this->correctionTransferReady = false;
    }

    public function postExpense(PostExpense $action): void
    {
        abort_if($this->correctionDocumentId !== null
            && $this->correctionDocumentType !== DocumentType::Expense->value,
            409, 'Düzenleme kaydı farklı bir işlem türüne ait.');
        DB::connection('period')->transaction(function () use ($action) {
            if ($this->correctionDocumentId !== null && ! $this->correctionDocumentReady) {
                $source = Document::query()->where('document_type', DocumentType::Expense->value)
                    ->where('status', 'posted')->lockForUpdate()
                    ->findOrFail($this->correctionDocumentId);
                app(ReverseDocument::class)->handle(
                    $source, now()->toDateString(),
                    'Gider düzenlendi; eski belge geçmişte saklandı.',
                    $this->childMutationKey('expense', 'old-revision'),
                );
            }
            $action->handle(
            $this->expenseCategory,
            $this->expenseNet,
            $this->expenseVatRate,
            $this->date,
            $this->expenseAccountType,
            (int) $this->expenseAccountId,
            $this->expenseCurrency,
            $this->expenseExchangeRate,
            ($this->correctionDocumentId !== null ? $this->childMutationKey('expense', 'new-revision') : $this->mutationKey('expense')),
            $this->note ?: null,
            );
        }, attempts: 3);
        $this->completeMutation('expense');
        if ($this->correctionDocumentType === DocumentType::Expense->value) {
            $this->correctionDocumentId = null;
            $this->correctionDocumentReady = false;
            $this->correctionDocumentType = '';
        }
    }

    public function postAdvance(PostAdvance $action): void
    {
        abort_if($this->correctionDocumentId !== null
            && ! in_array($this->correctionDocumentType, [
                DocumentType::Advance->value, DocumentType::AdvanceReturn->value,
            ], true),
            409, 'Düzenleme kaydı farklı bir işlem türüne ait.');
        DB::connection('period')->transaction(function () use ($action) {
            if ($this->correctionDocumentId !== null && ! $this->correctionDocumentReady) {
                $source = Document::query()
                    ->whereIn('document_type', [
                        DocumentType::Advance->value, DocumentType::AdvanceReturn->value,
                    ])->where('status', 'posted')->lockForUpdate()
                    ->findOrFail($this->correctionDocumentId);
                app(ReverseDocument::class)->handle(
                    $source, now()->toDateString(),
                    'Avans düzenlendi; eski belge geçmişte saklandı.',
                    $this->childMutationKey('advance', 'old-revision'),
                );
            }
            $action->handle(
            $this->advanceOperation,
            (int) $this->advanceContactId,
            $this->advanceAmount,
            $this->date,
            $this->advanceAccountType,
            (int) $this->advanceAccountId,
            $this->advanceCurrency,
            $this->advanceExchangeRate,
            ($this->correctionDocumentId !== null ? $this->childMutationKey('advance', 'new-revision') : $this->mutationKey('advance')),
            $this->note ?: null,
            );
        }, attempts: 3);
        $this->completeMutation('advance');
        if (in_array($this->correctionDocumentType, [
            DocumentType::Advance->value, DocumentType::AdvanceReturn->value,
        ], true)) {
            $this->correctionDocumentId = null;
            $this->correctionDocumentReady = false;
            $this->correctionDocumentType = '';
        }
    }

    /** Delete from the user list without exposing the accounting reversal step. */
    public function deleteFinanceDocument(int $id): void
    {
        $document = Document::query()->whereIn('document_type', [
            DocumentType::Expense->value, DocumentType::Advance->value,
            DocumentType::AdvanceReturn->value,
        ])->where('status', 'posted')->findOrFail($id);
        app(ReverseDocument::class)->handle(
            $document, now()->toDateString(),
            'Finans belgesi silindi; mali geçmiş korundu.',
            $this->childMutationKey('reverseDocument', 'delete:'.$id),
        );
        $this->completeMutation('reverseDocument');
        if ($this->openedKind === 'document' && $this->openedId === $id) {
            $this->closeOpenedRecord();
        }
        if ($this->correctionDocumentId === $id) {
            $this->cancelCorrection();
        }
    }

    public function deleteManualRecord(string $type, int $id): void
    {
        abort_unless(in_array($type, ['cash', 'bank'], true), 422);
        app(ReverseManualFinanceMovement::class)->handle(
            $type, $id, now()->toDateString(),
            'Manuel hareket silindi; mali geçmiş korundu.',
            $this->childMutationKey('reverseManual', 'delete:'.$type.':'.$id),
        );
        $this->completeMutation('reverseManual');
        if ($this->openedKind === 'manual' && $this->openedId === $id && $this->openedAccount === $type) {
            $this->closeOpenedRecord();
        }
        if ($this->correctionMovementId === $id && $this->correctionMovementAccountType === $type) {
            $this->cancelCorrection();
        }
    }

    public function deleteTransferRecord(string $group): void
    {
        abort_unless($group !== '' && mb_strlen($group) <= 255, 422);
        app(ReverseFinanceTransfer::class)->handle(
            $group, now()->toDateString(),
            'Finans virmanı silindi; mali geçmiş korundu.',
            $this->childMutationKey('reverseTransfer', 'delete:'.$group),
        );
        $this->completeMutation('reverseTransfer');
        if ($this->openedKind === 'transfer' && $this->openedGroup === $group) {
            $this->closeOpenedRecord();
        }
        if ($this->correctionTransferGroup === $group) {
            $this->cancelCorrection();
        }
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
        if ($this->correctionMovementId === $this->reverseMovementId
            && $this->correctionMovementAccountType === $this->reverseAccountType) {
            $this->correctionMovementReady = true;
        }
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
        if ($this->correctionTransferGroup !== ''
            && $this->correctionTransferGroup === $this->reverseTransferGroupKey) {
            $this->correctionTransferReady = true;
        }
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
        if ($this->correctionDocumentId === $this->reverseDocumentId) {
            $this->correctionDocumentReady = true;
        }
        $this->reverseDocumentId = null;
    }

    public function prepareDocumentCorrection(int $id): void
    {
        $document = Document::query()->with('lines')
            ->whereIn('document_type', [
                DocumentType::Expense->value,
                DocumentType::Advance->value,
                DocumentType::AdvanceReturn->value,
            ])->where('status', 'posted')->findOrFail($id);
        $permission = $document->document_type === DocumentType::Expense
            ? 'expenses' : 'advances';
        abort_unless(auth()->user()?->can($permission.'.view')
            && auth()->user()->can($permission.'.cancel')
            && auth()->user()->can($permission.'.create'), 403);
        abort_if(DocumentRelation::query()->where('relation_type', 'reversal_of')
            ->where(fn ($q) => $q->where('target_document_id', $id)
                ->orWhere('source_document_id', $id))->exists(),
            422, 'Belge ters kayıt fişi veya zaten terslenmiş.');

        $cash = CashMovement::query()->where('document_id', $id)->whereNull('reversal_of_id')->first();
        $bank = BankMovement::query()->where('document_id', $id)->whereNull('reversal_of_id')->first();
        if (! $cash && ! $bank) {
            throw new DomainException('Düzeltilecek belgenin kaynak kasa/banka hareketi bulunamadı.');
        }

        $accountType = $cash ? 'cash' : 'bank';
        $accountId = (int) ($cash?->cash_account_id ?? $bank?->bank_account_id);
        $this->date = $document->document_date->toDateString();
        $this->note = (string) ($document->notes ?? '');

        if ($document->document_type === DocumentType::Expense) {
            $this->expenseCategory = (string) ($document->requirements_snapshot['expense_category'] ?? '');
            $this->expenseNet = (string) ($document->lines->first()?->unit_price ?? '0');
            $this->expenseVatRate = (string) ($document->lines->first()?->vat_rate ?? '0');
            $this->expenseCurrency = $document->currency;
            $this->expenseExchangeRate = (string) $document->exchange_rate;
            $this->expenseAccountType = $accountType;
            $this->expenseAccountId = $accountId;
        } else {
            $this->advanceOperation = $document->document_type === DocumentType::Advance
                ? 'give' : 'return';
            $this->advanceContactId = $document->contact_id;
            $this->advanceAmount = (string) $document->grand_total;
            $this->advanceCurrency = $document->currency;
            $this->advanceExchangeRate = (string) $document->exchange_rate;
            $this->advanceAccountType = $accountType;
            $this->advanceAccountId = $accountId;
        }

        $this->correctionDocumentId = $id;
        $this->correctionDocumentType = $document->document_type->value;
        $this->correctionDocumentReady = false;
        $this->reverseDocumentId = $id;
        $this->reversalDate = now()->toDateString();
        $this->reversalReason = '';
    }

    public function prepareManualCorrection(string $type, int $id): void
    {
        abort_unless(auth()->user()?->can('finance_movements.view')
            && auth()->user()->can('finance_movements.cancel')
            && auth()->user()->can('finance_movements.create'), 403);
        abort_unless(in_array($type, ['cash','bank'], true), 422);

        $model = $type === 'cash' ? CashMovement::class : BankMovement::class;
        $movement = $model::query()->whereNull('document_id')
            ->whereNull('reversal_of_id')
            ->whereNotIn('movement_type', [
                'transfer', 'reversal', 'security_collection', 'security_payment', 'statement',
            ])->findOrFail($id);

        abort_if($type === 'bank' && $movement->origin !== 'book', 422);
        abort_if($model::query()->where('reversal_of_id', $id)->exists(),
            422, 'Hareket daha önce terslenmiş.');

        $this->accountType = $type;
        $this->accountId = $type === 'cash' ? $movement->cash_account_id : $movement->bank_account_id;
        $this->direction = $movement->direction;
        $this->amount = (string) $movement->amount;
        $this->contactId = $movement->contact_id;
        $this->date = $movement->movement_date->toDateString();
        $this->movementType = $movement->movement_type;
        $this->reference = (string) ($movement->reference ?? '');
        $this->note = (string) ($movement->description ?? '');
        $this->correctionMovementId = $id;
        $this->correctionMovementAccountType = $type;
        $this->correctionMovementReady = false;
        $this->reverseMovementId = $id;
        $this->reverseAccountType = $type;
        $this->reversalDate = now()->toDateString();
        $this->reversalReason = '';
    }

    public function prepareTransferCorrection(string $group): void
    {
        abort_unless(auth()->user()?->can('finance_transfers.view')
            && auth()->user()->can('finance_transfers.cancel')
            && auth()->user()->can('finance_transfers.create'), 403);

        $cash = CashMovement::query()->where('movement_type','transfer')
            ->where('group_key', $group)->get();
        $bank = BankMovement::query()->where('movement_type','transfer')
            ->where('origin', 'book')->where('group_key', $group)->get();

        $records = $cash->concat($bank);
        $out = $records->firstWhere('direction', 'out');
        $in = $records->firstWhere('direction', 'in');
        if ($records->count() !== 2 || $out === null || $in === null) {
            throw new DomainException('Virman kaynak hareket çifti bulunamadı.');
        }

        foreach ($records as $record) {
            $model = $record instanceof CashMovement ? CashMovement::class : BankMovement::class;
            abort_if($model::query()->where('reversal_of_id', $record->id)->exists(),
                422, 'Virman daha önce terslenmiş.');
        }

        $this->transferSourceType = $out instanceof CashMovement ? 'cash' : 'bank';
        $this->transferTargetType = $in instanceof CashMovement ? 'cash' : 'bank';
        $this->transferSourceId = $out instanceof CashMovement ? $out->cash_account_id : $out->bank_account_id;
        $this->transferTargetId = $in instanceof CashMovement ? $in->cash_account_id : $in->bank_account_id;
        $this->transferAmount = (string) $out->amount;
        $this->date = $out->movement_date->toDateString();
        $this->note = (string) ($out->description ?? '');
        $this->correctionTransferGroup = $group;
        $this->correctionTransferReady = false;
        $this->reverseTransferGroupKey = $group;
        $this->reversalDate = now()->toDateString();
        $this->reversalReason = '';
    }

    public function cancelCorrection(): void
    {
        $this->correctionDocumentId = null;
        $this->correctionDocumentType = '';
        $this->correctionDocumentReady = false;
        $this->correctionMovementId = null;
        $this->correctionMovementAccountType = '';
        $this->correctionMovementReady = false;
        $this->correctionTransferGroup = '';
        $this->correctionTransferReady = false;
        $this->reverseDocumentId = null;
        $this->reverseMovementId = null;
        $this->reverseTransferGroupKey = '';
    }

    public function render(): View
    {
        $user = auth()->user();
        $mayViewManual = $user?->can('finance_movements.view') ?? false;
        $mayViewTransfers = $user?->can('finance_transfers.view') ?? false;
        $mayViewExpenses = $user?->can('expenses.view') ?? false;
        $mayViewAdvances = $user?->can('advances.view') ?? false;

        $excluded = [
            'transfer', 'reversal', 'security_collection', 'security_payment', 'statement',
        ];
        $manualCash = CashMovement::query()->whereNull('document_id')->whereNull('reversal_of_id')
            ->whereNotIn('movement_type', $excluded)
            ->whereNotIn('id', CashMovement::query()->whereNotNull('reversal_of_id')
                ->select('reversal_of_id'))->orderByDesc('movement_date')->limit(35)->get();
        $manualBank = BankMovement::query()->where('origin', 'book')
            ->whereNull('document_id')->whereNull('reversal_of_id')
            ->whereNotIn('movement_type', $excluded)
            ->whereNotIn('id', BankMovement::query()->whereNotNull('reversal_of_id')
                ->select('reversal_of_id'))->orderByDesc('movement_date')->limit(35)->get();
        $transferCash = CashMovement::query()->where('movement_type', 'transfer')
            ->where('direction', 'out')->whereNotNull('group_key')
            ->whereNotIn('id', CashMovement::query()->whereNotNull('reversal_of_id')
                ->select('reversal_of_id'))
            ->orderByDesc('movement_date')->limit(35)->get();
        $transferBank = BankMovement::query()->where('movement_type', 'transfer')
            ->where('origin', 'book')->where('direction', 'out')->whereNotNull('group_key')
            ->whereNotIn('id', BankMovement::query()->whereNotNull('reversal_of_id')
                ->select('reversal_of_id'))
            ->orderByDesc('movement_date')->limit(35)->get();

        return view('livewire.finance.finance-operation-center', [
            'manualCorrectable' => $mayViewManual ? $manualCash->map(fn ($m) => ['account' => 'cash', 'movement' => $m])
                ->concat($manualBank->map(fn ($m) => ['account' => 'bank', 'movement' => $m]))
                ->sortByDesc(fn ($item) => $item['movement']->movement_date)->take(60) : collect(),
            'transferCorrectable' => $mayViewTransfers ? $transferCash->map(fn ($m) => $m->group_key)
                ->concat($transferBank->map(fn ($m) => $m->group_key))
                ->unique()->values() : collect(),
            'cashAccounts' => CashAccount::query()->where('is_active', true)->orderBy('code')->get(),
            'bankAccounts' => BankAccount::query()->where('is_active', true)->orderBy('code')->get(),
            'contacts' => Contact::query()->where('is_active', true)->orderBy('title')->limit(500)->get(),
            'openedFinanceDocument' => $this->openedKind === 'document'
                && ($mayViewExpenses || $mayViewAdvances)
                ? Document::query()->where('status', 'posted')
                    ->whereIn('document_type', [
                        ...($mayViewExpenses ? [DocumentType::Expense->value] : []),
                        ...($mayViewAdvances ? [DocumentType::Advance->value, DocumentType::AdvanceReturn->value] : []),
                    ])->find($this->openedId) : null,
            'openedManualMovement' => $this->openedKind === 'manual' && $mayViewManual
                && in_array($this->openedAccount, ['cash', 'bank'], true)
                ? ($this->openedAccount === 'cash' ? CashMovement::query() : BankMovement::query())
                    ->whereNull('document_id')->whereNull('reversal_of_id')->find($this->openedId) : null,
            'openedTransferMovement' => $this->openedKind === 'transfer' && $mayViewTransfers
                ? (CashMovement::query()->where('movement_type', 'transfer')->where('group_key', $this->openedGroup)->first()
                    ?? BankMovement::query()->where('movement_type', 'transfer')->where('origin', 'book')
                        ->where('group_key', $this->openedGroup)->first()) : null,
            'cashMovements' => $mayViewManual ? CashMovement::query()
                ->latest('movement_date')->latest('id')->limit(50)->get() : collect(),
            'bankMovements' => $mayViewManual ? BankMovement::query()
                ->where('origin', 'book')
                ->latest('movement_date')
                ->latest('id')
                ->limit(50)
                ->get() : collect(),
            'financeDocuments' => ($mayViewExpenses || $mayViewAdvances) ? Document::query()
                ->where('status', 'posted')
                ->whereDoesntHave('incomingRelations', fn ($q) => $q
                    ->where('relation_type', 'reversal_of'))
                ->whereDoesntHave('outgoingRelations', fn ($q) => $q
                    ->where('relation_type', 'reversal_of'))
                ->whereIn('document_type', [
                    ...($mayViewExpenses ? [DocumentType::Expense->value] : []),
                    ...($mayViewAdvances
                        ? [DocumentType::Advance->value, DocumentType::AdvanceReturn->value] : []),
                ])
                ->orderByDesc('document_date')
                ->orderByDesc('id')
                ->limit(100)
                ->get() : collect(),
        ])->layout('layouts.app', ['pageTitle' => 'Finans İşlemleri']);
    }
}

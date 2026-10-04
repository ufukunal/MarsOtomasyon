<?php

namespace App\Actions\Finance;

use App\Actions\Numbering\GenerateDocumentNumber;
use App\Actions\Periods\EnsurePeriodOpen;
use App\Models\Period\BankMovement;
use App\Models\Period\ContactTransaction;
use App\Models\Period\Security;
use App\Models\Period\SecurityPayroll;
use App\Support\Audit\AuditContext;
use App\Support\Auth\MutationAuthorizer;
use App\Support\Concurrency\IdempotencyKey;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

final class ReverseSecurityPayroll
{
    public function __construct(
        private readonly EnsurePeriodOpen $ensurePeriodOpen,
        private readonly GenerateDocumentNumber $numbers,
    ) {}

    public function handle(
        SecurityPayroll $payroll,
        string $reversalDate,
        string $reason,
        string $idempotencyKey,
    ): SecurityPayroll {
        MutationAuthorizer::authorize('security_payrolls.cancel');
        $reason = trim($reason);

        if ($reason === '') {
            throw new DomainException('Çek/senet bordro ters kayıt gerekçesi zorunludur.');
        }

        return IdempotencyKey::run(
            $idempotencyKey,
            'security-payroll.reverse:'.$payroll->id,
            function () use ($payroll, $reversalDate, $reason): SecurityPayroll {
                $date = CarbonImmutable::parse($reversalDate)->startOfDay();
                $this->ensurePeriodOpen->handle($date);

                return DB::connection('period')->transaction(function () use ($payroll, $date, $reason): SecurityPayroll {
                    $original = SecurityPayroll::query()->lockForUpdate()->findOrFail($payroll->id);

                    if ($original->action === 'reversal' || $original->status !== 'posted') {
                        throw new DomainException('Bu çek/senet bordrosu ters kayıt için uygun değil.');
                    }

                    if (SecurityPayroll::query()->where('reversal_of_id', $original->id)->exists()) {
                        throw new DomainException('Çek/senet bordrosu daha önce terslenmiş.');
                    }

                    $ids = array_values(array_unique(array_map('intval', $original->security_ids)));
                    $securities = Security::query()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();

                    if ($securities->count() !== count($ids)) {
                        throw new DomainException('Bordro çek/senet üyeliği bozulmuş.');
                    }

                    foreach ($securities as $security) {
                        if ((int) $security->last_payroll_id !== (int) $original->id) {
                            throw new DomainException(
                                "{$security->instrument_no} üzerinde bu bordrodan sonra başka işlem var; önce son işlem terslenmelidir.",
                            );
                        }
                    }

                    $snapshot = $original->state_snapshot;

                    if (! is_array($snapshot)) {
                        throw new DomainException('Bordro durum snapshotı bulunamadı.');
                    }

                    $actor = auth()->user();
                    $currentSnapshot = [];

                    foreach ($securities as $security) {
                        $currentSnapshot[(string) $security->id] = [
                            'status' => $security->status,
                            'contact_transaction_id' => $security->contact_transaction_id,
                            'endorsed_to_contact_id' => $security->endorsed_to_contact_id,
                            'bank_account_id' => $security->bank_account_id,
                            'last_payroll_id' => $security->last_payroll_id,
                        ];
                    }

                    $reversal = SecurityPayroll::query()->create([
                        'number' => $this->numbers->handle('security_payroll', $date->year),
                        'action' => 'reversal',
                        'contact_id' => $original->contact_id,
                        'bank_account_id' => $original->bank_account_id,
                        'reversal_of_id' => $original->id,
                        'payroll_date' => $date->toDateString(),
                        'currency' => $original->currency,
                        'total_amount' => $original->total_amount,
                        'security_ids' => $ids,
                        'state_snapshot' => $currentSnapshot,
                        'status' => 'posted',
                        'notes' => $reason,
                        'created_by' => $actor?->id,
                        'created_by_name' => $actor?->name,
                    ]);

                    foreach ($securities as $security) {
                        $state = $snapshot[(string) $security->id] ?? null;

                        if (! is_array($state) || ! isset($state['status'])) {
                            throw new DomainException('Bordro çek/senet snapshotı eksik.');
                        }

                        $activeContactTransactionId = $state['contact_transaction_id']
                            ?? $security->contact_transaction_id;

                        if (in_array($original->action, ['endorsement', 'return', 'protest', 'cancel'], true)) {
                            $reverseTransaction = $this->reverseContactEffect(
                                $original,
                                $reversal,
                                $security,
                                $date->toDateString(),
                                $actor?->id,
                                $actor?->name,
                            );

                            if (in_array($original->action, ['return', 'protest', 'cancel'], true)) {
                                $activeContactTransactionId = $reverseTransaction->id;
                            }
                        }

                        if (in_array($original->action, ['collection', 'payment'], true)) {
                            $this->reverseBankEffect(
                                $original,
                                $reversal,
                                $security,
                                $date->toDateString(),
                                $reason,
                                $actor?->id,
                                $actor?->name,
                            );
                        }

                        $security->updateWithVersion([
                            'status' => (string) $state['status'],
                            'contact_transaction_id' => $activeContactTransactionId,
                            'endorsed_to_contact_id' => $state['endorsed_to_contact_id'] ?? null,
                            'bank_account_id' => $state['bank_account_id'] ?? null,
                            'last_payroll_id' => $reversal->id,
                        ], (int) $security->version);
                    }

                    AuditContext::period(
                        'Çek/senet bordrosu ters kayıtla düzeltildi.',
                        [
                            'original_payroll_id' => $original->id,
                            'reversal_payroll_id' => $reversal->id,
                            'reason' => $reason,
                        ],
                        $reversal,
                        'security_payroll_reversed',
                    );

                    return $reversal;
                }, attempts: 3);
            },
        );
    }

    private function reverseContactEffect(
        SecurityPayroll $original,
        SecurityPayroll $reversal,
        Security $security,
        string $date,
        ?int $actorId,
        ?string $actorName,
    ): ContactTransaction {
        $transactionType = match ($original->action) {
            'endorsement' => 'security_endorsement',
            'return' => 'security_return',
            'protest' => 'security_protest',
            'cancel' => 'security_cancel',
            default => throw new DomainException('Bordro cari etkisi terslenemiyor.'),
        };
        $contactId = $original->action === 'endorsement'
            ? $original->contact_id
            : $security->contact_id;

        if ($contactId === null) {
            throw new DomainException('Bordro cari etkisi için cari bulunamadı.');
        }

        $transaction = ContactTransaction::query()
            ->where('contact_id', $contactId)
            ->where('transaction_type', $transactionType)
            ->where('description', $original->number.' · '.$security->instrument_no)
            ->where('amount', $security->amount)
            ->lockForUpdate()
            ->first();

        if (! $transaction) {
            throw new DomainException('Bordro cari etkisi bulunamadı.');
        }

        if (ContactTransaction::query()->where('reversal_of_id', $transaction->id)->exists()) {
            throw new DomainException('Bordro cari etkisi daha önce terslenmiş.');
        }

        return ContactTransaction::query()->create([
            'contact_id' => $transaction->contact_id,
            'transaction_type' => $transaction->transaction_type,
            'direction' => $transaction->direction === 'debit' ? 'credit' : 'debit',
            'transaction_date' => $date,
            'due_date' => $transaction->due_date,
            'amount' => $transaction->amount,
            'currency' => $transaction->currency,
            'reversal_of_id' => $transaction->id,
            'description' => $reversal->number.' · '.$security->instrument_no,
            'created_by' => $actorId,
            'created_by_name' => $actorName,
        ]);
    }

    private function reverseBankEffect(
        SecurityPayroll $original,
        SecurityPayroll $reversal,
        Security $security,
        string $date,
        string $reason,
        ?int $actorId,
        ?string $actorName,
    ): void {
        $movement = BankMovement::query()
            ->where('origin', 'book')
            ->whereRaw("metadata->>'payroll_id' = ?", [(string) $original->id])
            ->whereRaw("metadata->>'security_id' = ?", [(string) $security->id])
            ->lockForUpdate()
            ->first();

        if (! $movement) {
            throw new DomainException('Bordro banka etkisi bulunamadı.');
        }

        if (BankMovement::query()->where('reversal_of_id', $movement->id)->exists()) {
            throw new DomainException('Bordro banka etkisi daha önce terslenmiş.');
        }

        BankMovement::query()->create([
            'bank_account_id' => $movement->bank_account_id,
            'movement_date' => $date,
            'direction' => $movement->direction === 'in' ? 'out' : 'in',
            'movement_type' => 'security_reversal',
            'amount' => $movement->amount,
            'origin' => 'book',
            'reference' => 'REV-'.$security->instrument_no,
            'group_key' => hash('sha256', $reversal->number),
            'reversal_of_id' => $movement->id,
            'description' => $reason,
            'metadata' => [
                'security_id' => $security->id,
                'payroll_id' => $reversal->id,
                'original_payroll_id' => $original->id,
            ],
            'created_by' => $actorId,
            'created_by_name' => $actorName,
        ]);
    }
}

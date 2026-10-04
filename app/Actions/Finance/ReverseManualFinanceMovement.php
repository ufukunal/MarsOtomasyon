<?php

namespace App\Actions\Finance;

use App\Actions\Periods\EnsurePeriodOpen;
use App\Models\Period\BankMovement;
use App\Models\Period\CashMovement;
use App\Models\Period\ContactTransaction;
use App\Support\Audit\AuditContext;
use App\Support\Auth\MutationAuthorizer;
use App\Support\Concurrency\IdempotencyKey;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

final class ReverseManualFinanceMovement
{
    public function __construct(private readonly EnsurePeriodOpen $ensurePeriodOpen) {}

    public function handle(
        string $accountType,
        int $movementId,
        string $reversalDate,
        string $reason,
        string $idempotencyKey,
    ): CashMovement|BankMovement {
        MutationAuthorizer::authorize('finance_movements.cancel');
        $reason = trim($reason);

        if (! in_array($accountType, ['cash', 'bank'], true) || $reason === '') {
            throw new DomainException('Finans hareketi ters kayıt parametreleri geçersiz.');
        }

        return IdempotencyKey::run(
            $idempotencyKey,
            'finance-movement.reverse:'.$accountType.':'.$movementId,
            function () use ($accountType, $movementId, $reversalDate, $reason): CashMovement|BankMovement {
                $date = CarbonImmutable::parse($reversalDate)->startOfDay();
                $this->ensurePeriodOpen->handle($date);

                return DB::connection('period')->transaction(function () use (
                    $accountType,
                    $movementId,
                    $date,
                    $reason,
                ): CashMovement|BankMovement {
                    $movement = $accountType === 'cash'
                        ? CashMovement::query()->lockForUpdate()->findOrFail($movementId)
                        : BankMovement::query()->lockForUpdate()->findOrFail($movementId);

                    if ($movement->document_id !== null
                        || in_array($movement->movement_type, [
                            'transfer',
                            'reversal',
                            'security_collection',
                            'security_payment',
                            'statement',
                        ], true)
                        || ($movement instanceof BankMovement && $movement->origin !== 'book')) {
                        throw new DomainException('Bu finans hareketi manuel ters kayıt için uygun değil.');
                    }

                    $alreadyReversed = $accountType === 'cash'
                        ? CashMovement::query()->where('reversal_of_id', $movement->id)->exists()
                        : BankMovement::query()->where('reversal_of_id', $movement->id)->exists();

                    if ($alreadyReversed) {
                        throw new DomainException('Finans hareketi daha önce terslenmiş.');
                    }

                    $actor = auth()->user();
                    $metadata = $movement->metadata ?? [];
                    $sourceContactTransactionId = isset($metadata['contact_transaction_id'])
                        ? (int) $metadata['contact_transaction_id']
                        : null;
                    $reverseContactTransaction = null;

                    if ($movement->contact_id !== null && $sourceContactTransactionId === null) {
                        throw new DomainException('Cari etkili finans hareketinin kaynak cari hareketi bulunamadı.');
                    }

                    if ($sourceContactTransactionId !== null) {
                        $sourceTransaction = ContactTransaction::query()
                            ->lockForUpdate()
                            ->findOrFail($sourceContactTransactionId);

                        if (ContactTransaction::query()
                            ->where('reversal_of_id', $sourceTransaction->id)
                            ->exists()) {
                            throw new DomainException('Finans hareketinin cari etkisi daha önce terslenmiş.');
                        }

                        $reverseContactTransaction = ContactTransaction::query()->create([
                            'contact_id' => $sourceTransaction->contact_id,
                            'transaction_type' => $sourceTransaction->transaction_type,
                            'direction' => $sourceTransaction->direction === 'debit' ? 'credit' : 'debit',
                            'transaction_date' => $date->toDateString(),
                            'due_date' => $sourceTransaction->due_date,
                            'amount' => $sourceTransaction->amount,
                            'currency' => $sourceTransaction->currency,
                            'reversal_of_id' => $sourceTransaction->id,
                            'description' => $reason,
                            'created_by' => $actor?->id,
                            'created_by_name' => $actor?->name,
                        ]);
                    }

                    $reverseMetadata = [
                        ...$metadata,
                        'reversal_reason' => $reason,
                        'contact_transaction_id' => $reverseContactTransaction?->id,
                    ];
                    $attributes = [
                        'document_id' => null,
                        'contact_id' => $movement->contact_id,
                        'movement_date' => $date->toDateString(),
                        'direction' => $movement->direction === 'in' ? 'out' : 'in',
                        'movement_type' => 'reversal',
                        'amount' => $movement->amount,
                        'reference' => 'REV-'.$movement->id,
                        'group_key' => $movement->group_key,
                        'reversal_of_id' => $movement->id,
                        'description' => $reason,
                        'metadata' => $reverseMetadata,
                        'created_by' => $actor?->id,
                        'created_by_name' => $actor?->name,
                    ];

                    $reversal = $accountType === 'cash'
                        ? CashMovement::query()->create([
                            ...$attributes,
                            'cash_account_id' => $movement->cash_account_id,
                        ])
                        : BankMovement::query()->create([
                            ...$attributes,
                            'bank_account_id' => $movement->bank_account_id,
                            'origin' => 'book',
                        ]);

                    AuditContext::period(
                        'Manuel finans hareketi terslendi.',
                        [
                            'original_movement_id' => $movement->id,
                            'reversal_movement_id' => $reversal->id,
                            'account_type' => $accountType,
                            'reason' => $reason,
                        ],
                        $reversal,
                        'finance_movement_reversed',
                    );

                    return $reversal;
                }, attempts: 3);
            },
        );
    }
}

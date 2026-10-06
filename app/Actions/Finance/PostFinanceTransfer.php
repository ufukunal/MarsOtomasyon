<?php

namespace App\Actions\Finance;

use App\Actions\Periods\EnsurePeriodOpen;
use App\Models\Period\BankAccount;
use App\Models\Period\BankMovement;
use App\Models\Period\CashAccount;
use App\Models\Period\CashMovement;
use App\Support\Audit\AuditContext;
use App\Support\Auth\MutationAuthorizer;
use App\Support\Concurrency\IdempotencyKey;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final class PostFinanceTransfer
{
    public function __construct(private readonly EnsurePeriodOpen $ensurePeriodOpen) {}

    /** @return array{source:CashMovement|BankMovement,target:CashMovement|BankMovement} */
    public function handle(
        string $sourceType,
        int $sourceId,
        string $targetType,
        int $targetId,
        string $amount,
        string $movementDate,
        string $idempotencyKey,
        ?string $note = null,
    ): array {
        MutationAuthorizer::authorize('finance_transfers.create');

        return IdempotencyKey::run(
            $idempotencyKey,
            'finance-transfer.create',
            function () use (
                $sourceType,
                $sourceId,
                $targetType,
                $targetId,
                $amount,
                $movementDate,
                $idempotencyKey,
                $note,
            ): array {
                if (! in_array($sourceType, ['cash', 'bank'], true)
                    || ! in_array($targetType, ['cash', 'bank'], true)
                    || ($sourceType === $targetType && $sourceId === $targetId)) {
                    throw new DomainException('Virman kaynak/hedef hesapları geçersiz.');
                }

                $normalized = bcadd($amount, '0', 4);

                if (bccomp($normalized, '0', 4) <= 0) {
                    throw new DomainException('Virman tutarı pozitif olmalıdır.');
                }

                $date = CarbonImmutable::parse($movementDate)->startOfDay();
                $this->ensurePeriodOpen->handle($date);
                $groupKey = hash('sha256', 'finance-transfer:'.$idempotencyKey);

                return DB::connection('period')->transaction(function () use (
                    $sourceType,
                    $sourceId,
                    $targetType,
                    $targetId,
                    $normalized,
                    $date,
                    $groupKey,
                    $note,
                ): array {
                    ['source' => $sourceAccount, 'target' => $targetAccount] = $this->lockAccounts(
                        $sourceType,
                        $sourceId,
                        $targetType,
                        $targetId,
                    );

                    if ($sourceAccount->getAttribute('currency') !== $targetAccount->getAttribute('currency')) {
                        throw new DomainException('Virman yalnız aynı para birimli finans hesapları arasında yapılabilir.');
                    }

                    $actor = auth()->user();
                    $source = $this->createMovement(
                        $sourceType,
                        $sourceId,
                        'out',
                        $normalized,
                        $date->toDateString(),
                        $groupKey,
                        $note,
                        $actor?->id,
                        $actor?->name,
                    );
                    $target = $this->createMovement(
                        $targetType,
                        $targetId,
                        'in',
                        $normalized,
                        $date->toDateString(),
                        $groupKey,
                        $note,
                        $actor?->id,
                        $actor?->name,
                    );

                    AuditContext::period(
                        'Finans virmanı kesinleştirildi.',
                        [
                            'group_key' => $groupKey,
                            'source_type' => $sourceType,
                            'source_id' => $sourceId,
                            'target_type' => $targetType,
                            'target_id' => $targetId,
                            'amount' => $normalized,
                            'currency' => $sourceAccount->getAttribute('currency'),
                        ],
                        $source,
                        'finance_transfer_posted',
                    );

                    return ['source' => $source, 'target' => $target];
                }, attempts: 3);
            },
        );
    }

    /** @return array{source:Model,target:Model} */
    private function lockAccounts(
        string $sourceType,
        int $sourceId,
        string $targetType,
        int $targetId,
    ): array {
        $sourceFirst = strcmp($sourceType, $targetType) < 0
            || ($sourceType === $targetType && $sourceId < $targetId);

        if ($sourceFirst) {
            $source = $this->lockAccount($sourceType, $sourceId);
            $target = $this->lockAccount($targetType, $targetId);
        } else {
            $target = $this->lockAccount($targetType, $targetId);
            $source = $this->lockAccount($sourceType, $sourceId);
        }

        return ['source' => $source, 'target' => $target];
    }

    private function lockAccount(string $type, int $id): Model
    {
        return $type === 'cash'
            ? CashAccount::query()->where('is_active', true)->lockForUpdate()->findOrFail($id)
            : BankAccount::query()->where('is_active', true)->lockForUpdate()->findOrFail($id);
    }

    private function createMovement(
        string $type,
        int $accountId,
        string $direction,
        string $amount,
        string $date,
        string $groupKey,
        ?string $note,
        ?int $actorId,
        ?string $actorName,
    ): CashMovement|BankMovement {
        $attributes = [
            'movement_date' => $date,
            'direction' => $direction,
            'movement_type' => 'transfer',
            'amount' => $amount,
            'group_key' => $groupKey,
            'reference' => substr($groupKey, 0, 24),
            'description' => $note,
            'created_by' => $actorId,
            'created_by_name' => $actorName,
        ];

        if ($type === 'cash') {
            return CashMovement::query()->create([
                ...$attributes,
                'cash_account_id' => $accountId,
            ]);
        }

        return BankMovement::query()->create([
            ...$attributes,
            'bank_account_id' => $accountId,
            'origin' => 'book',
        ]);
    }
}

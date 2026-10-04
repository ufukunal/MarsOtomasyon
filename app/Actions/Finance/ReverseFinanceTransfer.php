<?php

namespace App\Actions\Finance;

use App\Actions\Periods\EnsurePeriodOpen;
use App\Models\Period\BankMovement;
use App\Models\Period\CashMovement;
use App\Support\Audit\AuditContext;
use App\Support\Auth\MutationAuthorizer;
use App\Support\Concurrency\IdempotencyKey;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

final class ReverseFinanceTransfer
{
    public function __construct(private readonly EnsurePeriodOpen $ensurePeriodOpen) {}

    /** @return list<CashMovement|BankMovement> */
    public function handle(
        string $groupKey,
        string $reversalDate,
        string $reason,
        string $idempotencyKey,
    ): array {
        MutationAuthorizer::authorize('finance_transfers.cancel');
        $groupKey = trim($groupKey);
        $reason = trim($reason);

        if ($groupKey === '' || $reason === '') {
            throw new DomainException('Virman ters kaydı için grup anahtarı ve gerekçe zorunludur.');
        }

        return IdempotencyKey::run(
            $idempotencyKey,
            'finance-transfer.reverse:'.$groupKey,
            function () use ($groupKey, $reversalDate, $reason): array {
                $date = CarbonImmutable::parse($reversalDate)->startOfDay();
                $this->ensurePeriodOpen->handle($date);

                return DB::connection('period')->transaction(function () use (
                    $groupKey,
                    $date,
                    $reason,
                ): array {
                    $cash = CashMovement::query()
                        ->where('movement_type', 'transfer')
                        ->where('group_key', $groupKey)
                        ->lockForUpdate()
                        ->get();
                    $bank = BankMovement::query()
                        ->where('movement_type', 'transfer')
                        ->where('origin', 'book')
                        ->where('group_key', $groupKey)
                        ->lockForUpdate()
                        ->get();
                    $movements = $cash->concat($bank)->values();

                    if ($movements->count() !== 2
                        || $movements->where('direction', 'in')->count() !== 1
                        || $movements->where('direction', 'out')->count() !== 1) {
                        throw new DomainException('Virman kaynak hareket çifti geçersiz.');
                    }

                    foreach ($movements as $movement) {
                        $exists = $movement instanceof CashMovement
                            ? CashMovement::query()->where('reversal_of_id', $movement->id)->exists()
                            : BankMovement::query()->where('reversal_of_id', $movement->id)->exists();

                        if ($exists) {
                            throw new DomainException('Virman daha önce terslenmiş.');
                        }
                    }

                    $actor = auth()->user();
                    $reversalGroup = hash('sha256', 'reverse:'.$groupKey);
                    $created = [];

                    foreach ($movements as $movement) {
                        $attributes = [
                            'contact_id' => null,
                            'movement_date' => $date->toDateString(),
                            'direction' => $movement->direction === 'in' ? 'out' : 'in',
                            'movement_type' => 'reversal',
                            'amount' => $movement->amount,
                            'reference' => 'REV-'.substr($groupKey, 0, 16),
                            'group_key' => $reversalGroup,
                            'reversal_of_id' => $movement->id,
                            'description' => $reason,
                            'metadata' => [
                                'reversal_reason' => $reason,
                                'original_group_key' => $groupKey,
                            ],
                            'created_by' => $actor?->id,
                            'created_by_name' => $actor?->name,
                        ];

                        $created[] = $movement instanceof CashMovement
                            ? CashMovement::query()->create([
                                ...$attributes,
                                'cash_account_id' => $movement->cash_account_id,
                            ])
                            : BankMovement::query()->create([
                                ...$attributes,
                                'bank_account_id' => $movement->bank_account_id,
                                'origin' => 'book',
                            ]);
                    }

                    AuditContext::period(
                        'Finans virmanı terslendi.',
                        [
                            'original_group_key' => $groupKey,
                            'reversal_group_key' => $reversalGroup,
                            'reason' => $reason,
                        ],
                        $created[0],
                        'finance_transfer_reversed',
                    );

                    return $created;
                }, attempts: 3);
            },
        );
    }
}

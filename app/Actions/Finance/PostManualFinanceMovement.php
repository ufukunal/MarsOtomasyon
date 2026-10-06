<?php

namespace App\Actions\Finance;

use App\Actions\Periods\EnsurePeriodOpen;
use App\Models\Period\BankAccount;
use App\Models\Period\BankMovement;
use App\Models\Period\CashAccount;
use App\Models\Period\CashMovement;
use App\Models\Period\Contact;
use App\Models\Period\ContactTransaction;
use App\Support\Audit\AuditContext;
use App\Support\Auth\MutationAuthorizer;
use App\Support\Concurrency\IdempotencyKey;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

final class PostManualFinanceMovement
{
    public function __construct(private readonly EnsurePeriodOpen $ensurePeriodOpen) {}

    public function handle(
        string $accountType,
        int $accountId,
        string $direction,
        string $amount,
        string $movementDate,
        string $idempotencyKey,
        ?int $contactId = null,
        string $exchangeRate = '1.000000',
        string $movementType = 'manual',
        ?string $reference = null,
        ?string $note = null,
    ): CashMovement|BankMovement {
        MutationAuthorizer::authorize('finance_movements.create');

        return IdempotencyKey::run(
            $idempotencyKey,
            'finance-movement.create',
            function () use (
                $accountType,
                $accountId,
                $direction,
                $amount,
                $movementDate,
                $contactId,
                $exchangeRate,
                $movementType,
                $reference,
                $note,
            ): CashMovement|BankMovement {
                if (! in_array($accountType, ['cash', 'bank'], true)
                    || ! in_array($direction, ['in', 'out'], true)) {
                    throw new DomainException('Finans hareketi hesap türü veya yönü geçersiz.');
                }

                $normalized = bcadd($amount, '0', 4);
                $requestedRate = bcadd($exchangeRate, '0', 6);
                $movementType = trim($movementType);

                if (bccomp($normalized, '0', 4) <= 0
                    || bccomp($requestedRate, '0', 6) <= 0
                    || $movementType === '') {
                    throw new DomainException('Finans hareketi tutarı, kur ve işlem türü geçerli olmalıdır.');
                }

                if (in_array($movementType, ['transfer', 'reversal', 'statement'], true)) {
                    throw new DomainException('Bu işlem türü manuel finans hareketinde kullanılamaz.');
                }

                $date = CarbonImmutable::parse($movementDate)->startOfDay();
                $this->ensurePeriodOpen->handle($date);

                return DB::connection('period')->transaction(function () use (
                    $accountType,
                    $accountId,
                    $direction,
                    $normalized,
                    $date,
                    $contactId,
                    $movementType,
                    $reference,
                    $note,
                ): CashMovement|BankMovement {
                    $actor = auth()->user();
                    $contact = $contactId === null
                        ? null
                        : Contact::query()->lockForUpdate()->findOrFail($contactId);
                    $account = $accountType === 'cash'
                        ? CashAccount::query()->where('is_active', true)->lockForUpdate()->findOrFail($accountId)
                        : BankAccount::query()->where('is_active', true)->lockForUpdate()->findOrFail($accountId);
                    $currency = (string) $account->getAttribute('currency');

                    if ($currency !== 'TRY') {
                        throw new DomainException('Manuel finans hareketi yalnız TRY hesapta kullanılabilir; döviz hareketi alış/ithalat kaynağından gelmelidir.');
                    }

                    $rate = '1.000000';
                    $contactTransaction = null;

                    if ($contact !== null) {
                        $contactAmountTry = bcadd(bcmul($normalized, $rate, 8), '0', 4);

                        $contactTransaction = ContactTransaction::query()->create([
                            'contact_id' => $contact->id,
                            'transaction_type' => $movementType,
                            'direction' => $direction === 'in' ? 'credit' : 'debit',
                            'transaction_date' => $date->toDateString(),
                            'amount' => $contactAmountTry,
                            'currency' => 'TRY',
                            'description' => $note,
                            'created_by' => $actor?->id,
                            'created_by_name' => $actor?->name,
                        ]);
                    }

                    $metadata = [
                        'exchange_rate' => $rate,
                        'currency' => $currency,
                        'contact_transaction_id' => $contactTransaction?->id,
                    ];

                    if ($accountType === 'cash') {
                        $movement = CashMovement::query()->create([
                            'cash_account_id' => $account->id,
                            'contact_id' => $contact?->id,
                            'movement_date' => $date->toDateString(),
                            'direction' => $direction,
                            'movement_type' => $movementType,
                            'amount' => $normalized,
                            'reference' => $reference,
                            'description' => $note,
                            'metadata' => $metadata,
                            'created_by' => $actor?->id,
                            'created_by_name' => $actor?->name,
                        ]);
                    } else {
                        $movement = BankMovement::query()->create([
                            'bank_account_id' => $account->id,
                            'contact_id' => $contact?->id,
                            'movement_date' => $date->toDateString(),
                            'direction' => $direction,
                            'movement_type' => $movementType,
                            'amount' => $normalized,
                            'origin' => 'book',
                            'reference' => $reference,
                            'description' => $note,
                            'metadata' => $metadata,
                            'created_by' => $actor?->id,
                            'created_by_name' => $actor?->name,
                        ]);
                    }

                    AuditContext::period(
                        'Manuel finans hareketi kesinleştirildi.',
                        [
                            'account_type' => $accountType,
                            'account_id' => $accountId,
                            'direction' => $direction,
                            'movement_type' => $movementType,
                            'amount' => $normalized,
                            'currency' => $currency,
                            'contact_id' => $contact?->id,
                        ],
                        $movement,
                        'finance_movement_posted',
                    );

                    return $movement;
                }, attempts: 3);
            },
        );
    }
}

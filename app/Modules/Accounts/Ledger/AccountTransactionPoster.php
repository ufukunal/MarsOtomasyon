<?php

namespace App\Modules\Accounts\Ledger;

use App\Foundation\Clock\Clock;
use App\Foundation\Idempotency\IdempotencyStatus;
use App\Foundation\Idempotency\IdempotencyStore;
use App\Foundation\Idempotency\RequestFingerprint;
use App\Modules\Accounts\Models\Account;
use App\Modules\Accounts\Models\AccountTransaction;
use App\Modules\Core\Posting\PostingPeriodGuard;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use LogicException;

final readonly class AccountTransactionPoster
{
    private const IDEMPOTENCY_SCOPE = 'accounts.transaction';

    public function __construct(
        private PostingPeriodGuard $postingPeriods,
        private IdempotencyStore $idempotency,
        private AccountAmountNormalizer $amounts,
        private Clock $clock,
    ) {}

    public function post(PostAccountTransactionData $data): AccountTransaction
    {
        $this->assertInsideTransaction();

        $companyId = $data->sourceEffect->companyId;
        $account = Account::query()
            ->where('company_id', $companyId)
            ->whereKey($data->accountId)
            ->sharedLock()
            ->firstOrFail();

        $currencyCode = (string) $account->book_currency_code;
        $signedAmount = $this->amounts->normalize($data->signedAmount);
        $memo = $this->normalizeMemo($data->memo);
        $period = $this->postingPeriods->assertOpen($companyId, $data->postingDate);
        $effectFingerprint = $data->sourceEffect->fingerprint();

        $claim = $this->idempotency->claim(
            self::IDEMPOTENCY_SCOPE,
            $effectFingerprint,
            RequestFingerprint::fromPayload([
                'account_id' => $data->accountId,
                'posting_date' => $data->postingDate,
                'currency_code' => $currencyCode,
                'signed_amount' => $signedAmount,
                'memo' => $memo,
                'reversal_of_transaction_id' => $data->reversalOfTransactionId,
            ]),
        );

        if ($claim->isReplay()) {
            if ($claim->status !== IdempotencyStatus::Completed) {
                throw new LogicException('Cari hareket idempotency kaydı tamamlanmamış durumda bırakılamaz.');
            }

            $existing = AccountTransaction::query()
                ->where('effect_fingerprint', $effectFingerprint)
                ->first();

            if (! $existing instanceof AccountTransaction) {
                throw new LogicException('Tamamlanmış cari hareket idempotency kaydının ledger satırı bulunamadı.');
            }

            return $existing;
        }

        if ($data->reversalOfTransactionId !== null) {
            $this->assertReversal(
                companyId: $companyId,
                accountId: $data->accountId,
                currencyCode: $currencyCode,
                signedAmount: $signedAmount,
                originalId: $data->reversalOfTransactionId,
            );
        }

        try {
            $transaction = AccountTransaction::query()->create([
                'company_id' => $companyId,
                'account_id' => $data->accountId,
                'posting_period_id' => $period->getKey(),
                'posting_date' => $data->postingDate,
                'currency_code' => $currencyCode,
                'signed_amount' => $signedAmount,
                'source_type' => $data->sourceEffect->sourceType,
                'source_id' => $data->sourceEffect->sourceId,
                'effect_type' => $data->sourceEffect->effectType,
                'effect_fingerprint' => $effectFingerprint,
                'memo' => $memo,
                'reversal_of_transaction_id' => $data->reversalOfTransactionId,
                'created_at' => $this->clock->now(),
            ]);
        } catch (QueryException $exception) {
            if ($data->reversalOfTransactionId !== null && (string) $exception->getCode() === '23505') {
                throw new DomainException('Cari hareket daha önce ters kayıt ile kapatılmış.', previous: $exception);
            }

            throw $exception;
        }

        $this->idempotency->complete($claim);

        return $transaction;
    }

    private function assertReversal(
        int $companyId,
        int $accountId,
        string $currencyCode,
        string $signedAmount,
        int $originalId,
    ): void {
        $original = AccountTransaction::query()
            ->where('company_id', $companyId)
            ->where('account_id', $accountId)
            ->whereKey($originalId)
            ->sharedLock()
            ->first();

        if (! $original instanceof AccountTransaction) {
            throw new DomainException('Ters kayıt hedefi bu cariye ait değil.');
        }

        if ((string) $original->currency_code !== $currencyCode
            || $this->amounts->negate((string) $original->signed_amount) !== $signedAmount) {
            throw new DomainException('Ters kayıt aynı cari ve para birimindeki tutarı tam olarak ters çevirmelidir.');
        }

        if (AccountTransaction::query()->where('reversal_of_transaction_id', $originalId)->exists()) {
            throw new DomainException('Cari hareket daha önce ters kayıt ile kapatılmış.');
        }
    }

    private function normalizeMemo(?string $memo): ?string
    {
        if ($memo === null) {
            return null;
        }

        $memo = trim($memo);
        if ($memo === '') {
            return null;
        }
        if (mb_strlen($memo) > 500) {
            throw new DomainException('Cari hareket açıklaması en fazla 500 karakter olabilir.');
        }

        return $memo;
    }

    private function assertInsideTransaction(): void
    {
        if (DB::connection()->transactionLevel() < 1) {
            throw new LogicException('Cari hareket posting aynı business transaction içinde çalışmalıdır.');
        }
    }
}

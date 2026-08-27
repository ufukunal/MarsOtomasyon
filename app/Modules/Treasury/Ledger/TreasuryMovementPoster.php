<?php

namespace App\Modules\Treasury\Ledger;

use App\Foundation\Clock\Clock;
use App\Foundation\Idempotency\IdempotencyStatus;
use App\Foundation\Idempotency\IdempotencyStore;
use App\Foundation\Idempotency\RequestFingerprint;
use App\Modules\Accounts\Ledger\AccountAmountNormalizer;
use App\Modules\Core\Posting\PostingPeriodGuard;
use App\Modules\Treasury\Models\TreasuryAccount;
use App\Modules\Treasury\Models\TreasuryMovement;
use DomainException;
use Illuminate\Support\Facades\DB;
use LogicException;

final readonly class TreasuryMovementPoster
{
    private const IDEMPOTENCY_SCOPE = 'treasury.movement';

    public function __construct(
        private PostingPeriodGuard $postingPeriods,
        private IdempotencyStore $idempotency,
        private AccountAmountNormalizer $amounts,
        private Clock $clock,
    ) {}

    public function post(PostTreasuryMovementData $data): TreasuryMovement
    {
        if (DB::connection()->transactionLevel() < 1) {
            throw new LogicException('Treasury movement posting aynı business transaction içinde çalışmalıdır.');
        }

        $companyId = $data->sourceEffect->companyId;
        $account = TreasuryAccount::query()
            ->where('company_id', $companyId)
            ->whereKey($data->treasuryAccountId)
            ->lockForUpdate()
            ->firstOrFail();

        if (! (bool) $account->is_active && $data->reversalOfMovementId === null) {
            throw new DomainException('Yeni treasury hareketi aktif hesap gerektirir.');
        }

        $currency = (string) $account->currency_code;
        $amount = $this->amounts->normalize($data->signedAmount);
        $period = $this->postingPeriods->assertOpen($companyId, $data->postingDate);
        $memo = $this->memo($data->memo);
        $fingerprint = $data->sourceEffect->fingerprint();

        $claim = $this->idempotency->claim(
            self::IDEMPOTENCY_SCOPE,
            $fingerprint,
            RequestFingerprint::fromPayload([
                'treasury_account_id' => $data->treasuryAccountId,
                'posting_date' => $data->postingDate,
                'currency_code' => $currency,
                'signed_amount' => $amount,
                'movement_type' => $data->movementType,
                'account_id' => $data->accountId,
                'payment_method_id' => $data->paymentMethodId,
                'memo' => $memo,
                'reversal_of_movement_id' => $data->reversalOfMovementId,
            ]),
        );

        if ($claim->isReplay()) {
            if ($claim->status !== IdempotencyStatus::Completed) {
                throw new LogicException('Treasury movement idempotency kaydı tamamlanmamış bırakılamaz.');
            }

            $existing = TreasuryMovement::query()
                ->where('effect_fingerprint', $fingerprint)
                ->first();
            if (! $existing instanceof TreasuryMovement) {
                throw new LogicException('Tamamlanmış treasury idempotency kaydının ledger satırı bulunamadı.');
            }

            return $existing;
        }

        if ($data->reversalOfMovementId !== null) {
            $original = TreasuryMovement::query()
                ->where('company_id', $companyId)
                ->where('treasury_account_id', $data->treasuryAccountId)
                ->whereKey($data->reversalOfMovementId)
                ->sharedLock()
                ->first();

            if (! $original instanceof TreasuryMovement
                || (string) $original->currency_code !== $currency
                || $this->amounts->negate((string) $original->signed_amount) !== $amount) {
                throw new DomainException('Treasury ters kayıt aynı hesap ve tutarı tam olarak ters çevirmelidir.');
            }

            if (TreasuryMovement::query()->where('reversal_of_movement_id', $original->getKey())->exists()) {
                throw new DomainException('Treasury hareketi daha önce ters kayıt ile kapatılmış.');
            }
        }

        $movement = TreasuryMovement::query()->create([
            'company_id' => $companyId,
            'treasury_account_id' => $data->treasuryAccountId,
            'posting_period_id' => $period->getKey(),
            'posting_date' => $data->postingDate,
            'currency_code' => $currency,
            'signed_amount' => $amount,
            'movement_type' => $data->movementType,
            'account_id' => $data->accountId,
            'payment_method_id' => $data->paymentMethodId,
            'source_type' => $data->sourceEffect->sourceType,
            'source_id' => $data->sourceEffect->sourceId,
            'effect_type' => $data->sourceEffect->effectType,
            'effect_fingerprint' => $fingerprint,
            'memo' => $memo,
            'reversal_of_movement_id' => $data->reversalOfMovementId,
            'occurred_at' => $this->clock->now(),
            'created_at' => $this->clock->now(),
        ]);

        $this->idempotency->complete($claim);

        return $movement;
    }

    private function memo(?string $memo): ?string
    {
        if ($memo === null || trim($memo) === '') {
            return null;
        }

        $memo = trim($memo);
        if (mb_strlen($memo) > 500) {
            throw new DomainException('Treasury açıklaması en fazla 500 karakter olabilir.');
        }

        return $memo;
    }
}

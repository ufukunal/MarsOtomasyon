<?php

namespace App\Modules\Treasury\Actions;

use App\Foundation\Clock\Clock;
use App\Foundation\Identity\SourceEffectIdentity;
use App\Modules\Accounts\Ledger\AccountAmountNormalizer;
use App\Modules\Accounts\Ledger\AccountTransactionPoster;
use App\Modules\Accounts\Ledger\PostAccountTransactionData;
use App\Modules\Core\Audit\AuditRecorder;
use App\Modules\Core\Enums\AuditAction;
use App\Modules\Core\Enums\AuditTargetType;
use App\Modules\Treasury\Ledger\PostTreasuryMovementData;
use App\Modules\Treasury\Ledger\TreasuryMovementPoster;
use App\Modules\Treasury\Models\TreasuryAccount;
use App\Modules\Treasury\Models\TreasuryMovement;
use App\Modules\Treasury\Models\TreasuryPayment;
use DomainException;
use Illuminate\Support\Facades\DB;
use LogicException;

final readonly class TreasuryOperations
{
    public function __construct(
        private TreasuryMovementPoster $treasury,
        private AccountTransactionPoster $accounts,
        private AccountAmountNormalizer $amounts,
        private AuditRecorder $audit,
        private Clock $clock,
    ) {}

    public function finalizePayment(int $companyId, int $paymentId): TreasuryPayment
    {
        return DB::transaction(function () use ($companyId, $paymentId): TreasuryPayment {
            $payment = TreasuryPayment::query()
                ->where('company_id', $companyId)
                ->whereKey($paymentId)
                ->lockForUpdate()
                ->firstOrFail();

            if ((string) $payment->status === 'finalized') {
                return $payment;
            }
            if ((string) $payment->status !== 'draft') {
                throw new DomainException('Yalnız taslak tahsilat/ödeme kesinleştirilebilir.');
            }

            $amount = $this->positive((string) $payment->amount);
            $collection = (string) $payment->direction === 'collection';
            $treasuryAmount = $collection ? $amount : $this->amounts->negate($amount);
            $accountAmount = $this->amounts->negate($treasuryAmount);
            $sourceId = (string) $payment->getKey();
            $kind = (string) $payment->payment_kind;

            $treasuryMovement = $this->treasury->post(new PostTreasuryMovementData(
                sourceEffect: new SourceEffectIdentity(
                    $companyId,
                    'treasury_payment',
                    $sourceId,
                    $collection ? 'treasury.collection' : 'treasury.payment',
                ),
                treasuryAccountId: (int) $payment->treasury_account_id,
                postingDate: $payment->payment_date->format('Y-m-d'),
                signedAmount: $treasuryAmount,
                movementType: $collection && in_array($kind, ['pos', 'virtual_pos'], true)
                    ? 'pos_pending'
                    : ($collection ? 'collection' : 'payment'),
                accountId: (int) $payment->account_id,
                paymentMethodId: (int) $payment->payment_method_id,
                memo: $payment->note === null ? null : (string) $payment->note,
            ));

            $accountTransaction = $this->accounts->post(new PostAccountTransactionData(
                accountId: (int) $payment->account_id,
                postingDate: $payment->payment_date->format('Y-m-d'),
                signedAmount: $accountAmount,
                sourceEffect: new SourceEffectIdentity(
                    $companyId,
                    'treasury_payment',
                    $sourceId,
                    $collection ? 'account.collection' : 'account.payment',
                ),
                memo: $payment->note === null ? null : (string) $payment->note,
            ));

            $payment->forceFill([
                'status' => 'finalized',
                'finalized_at' => $this->clock->now(),
            ])->save();

            $this->audit->record(
                AuditAction::TreasuryPaymentFinalized,
                AuditTargetType::TreasuryPayment,
                $payment->getKey(),
                before: ['status' => 'draft'],
                after: ['status' => 'finalized', 'pos_status' => $payment->pos_status],
                metadata: [
                    'direction' => $payment->direction,
                    'payment_kind' => $payment->payment_kind,
                    'amount' => $amount,
                    'currency_code' => $payment->currency_code,
                    'treasury_movement_id' => $treasuryMovement->getKey(),
                    'account_transaction_id' => $accountTransaction->getKey(),
                ],
            );

            return $payment->refresh();
        }, 3);
    }

    public function reversePayment(int $companyId, int $paymentId): TreasuryPayment
    {
        return DB::transaction(function () use ($companyId, $paymentId): TreasuryPayment {
            $payment = TreasuryPayment::query()
                ->where('company_id', $companyId)
                ->whereKey($paymentId)
                ->lockForUpdate()
                ->firstOrFail();

            if ((string) $payment->status === 'reversed') {
                return $payment;
            }
            if ((string) $payment->status !== 'finalized') {
                throw new DomainException('Yalnız kesinleşmiş tahsilat/ödeme ters çevrilebilir.');
            }
            if (in_array((string) $payment->payment_kind, ['pos', 'virtual_pos'], true)
                && (string) $payment->pos_status === 'settled') {
                throw new DomainException('Settled POS işlemi reversal yerine chargeback ile kapatılmalıdır.');
            }

            $originalTreasury = TreasuryMovement::query()
                ->where('company_id', $companyId)
                ->where('source_type', 'treasury_payment')
                ->where('source_id', (string) $payment->getKey())
                ->firstOrFail();
            $originalAccount = DB::table('account_transactions')
                ->where('company_id', $companyId)
                ->where('source_type', 'treasury_payment')
                ->where('source_id', (string) $payment->getKey())
                ->first();
            if ($originalAccount === null) {
                throw new DomainException('Tahsilat/ödeme cari kaydı bulunamadı.');
            }

            $treasuryAmount = $this->amounts->negate((string) $originalTreasury->signed_amount);
            $accountAmount = $this->amounts->negate((string) $originalAccount->signed_amount);
            $sourceId = (string) $payment->getKey();
            $collection = (string) $payment->direction === 'collection';

            $treasuryReversal = $this->treasury->post(new PostTreasuryMovementData(
                sourceEffect: new SourceEffectIdentity(
                    $companyId,
                    'treasury_payment_reversal',
                    $sourceId,
                    $collection ? 'treasury.collection_reversal' : 'treasury.payment_reversal',
                ),
                treasuryAccountId: (int) $payment->treasury_account_id,
                postingDate: $payment->payment_date->format('Y-m-d'),
                signedAmount: $treasuryAmount,
                movementType: in_array((string) $payment->payment_kind, ['pos', 'virtual_pos'], true)
                    ? 'pos_reversal'
                    : ($collection ? 'payment' : 'collection'),
                accountId: (int) $payment->account_id,
                paymentMethodId: (int) $payment->payment_method_id,
                memo: 'Tahsilat/ödeme ters kaydı',
                reversalOfMovementId: (int) $originalTreasury->getKey(),
            ));

            $accountReversal = $this->accounts->post(new PostAccountTransactionData(
                accountId: (int) $payment->account_id,
                postingDate: $payment->payment_date->format('Y-m-d'),
                signedAmount: $accountAmount,
                sourceEffect: new SourceEffectIdentity(
                    $companyId,
                    'treasury_payment_reversal',
                    $sourceId,
                    $collection ? 'account.collection_reversal' : 'account.payment_reversal',
                ),
                memo: 'Tahsilat/ödeme ters kaydı',
                reversalOfTransactionId: (int) $originalAccount->id,
            ));

            $attributes = [
                'status' => 'reversed',
                'reversed_at' => $this->clock->now(),
            ];
            if (in_array((string) $payment->payment_kind, ['pos', 'virtual_pos'], true)) {
                $attributes['pos_status'] = 'reversed';
            }
            $payment->forceFill($attributes)->save();

            $this->audit->record(
                AuditAction::TreasuryPaymentReversed,
                AuditTargetType::TreasuryPayment,
                $payment->getKey(),
                before: ['status' => 'finalized'],
                after: ['status' => 'reversed', 'pos_status' => $payment->pos_status],
                metadata: [
                    'treasury_reversal_id' => $treasuryReversal->getKey(),
                    'account_reversal_id' => $accountReversal->getKey(),
                ],
            );

            return $payment->refresh();
        }, 3);
    }

    public function settlePos(
        int $companyId,
        int $paymentId,
        int $bankAccountId,
        string $date,
        string $commission,
    ): int {
        return DB::transaction(function () use (
            $companyId,
            $paymentId,
            $bankAccountId,
            $date,
            $commission,
        ): int {
            $payment = TreasuryPayment::query()
                ->where('company_id', $companyId)
                ->whereKey($paymentId)
                ->lockForUpdate()
                ->firstOrFail();

            if ((string) $payment->status !== 'finalized') {
                throw new DomainException('POS settlement için kesinleşmiş tahsilat gerekir.');
            }
            if (! in_array((string) $payment->payment_kind, ['pos', 'virtual_pos'], true)) {
                throw new DomainException('İşlem POS/Sanal POS değildir.');
            }

            $gross = $this->positive((string) $payment->amount);
            $commission = $this->nonNegative($commission);
            $net = $this->subtract($gross, $commission);
            if (str_starts_with($net, '-') || $net === '0.000000') {
                throw new DomainException('POS komisyonu brüt tahsilattan küçük olmalıdır.');
            }

            if ((string) $payment->pos_status === 'settled') {
                $existing = DB::table('treasury_pos_settlements')
                    ->where('company_id', $companyId)
                    ->where('treasury_payment_id', $paymentId)
                    ->first();
                if ($existing !== null
                    && (int) $existing->bank_account_id === $bankAccountId
                    && (string) $existing->settlement_date === $date
                    && (string) $existing->commission_amount === $commission) {
                    return (int) $existing->id;
                }

                throw new DomainException('POS tahsilatı daha önce farklı settlement içeriğiyle kapatılmış.');
            }
            if ((string) $payment->pos_status !== 'pending') {
                throw new DomainException('Yalnız pending POS tahsilatı settlement yapılabilir.');
            }

            $bank = TreasuryAccount::query()
                ->where('company_id', $companyId)
                ->whereKey($bankAccountId)
                ->lockForUpdate()
                ->firstOrFail();
            if ((string) $bank->type !== 'bank'
                || ! (bool) $bank->is_active
                || (string) $bank->currency_code !== (string) $payment->currency_code) {
                throw new DomainException('Settlement aynı para biriminde aktif banka hesabına yapılmalıdır.');
            }

            $settlementId = (int) DB::table('treasury_pos_settlements')->insertGetId([
                'company_id' => $companyId,
                'treasury_payment_id' => $payment->getKey(),
                'source_pos_account_id' => $payment->treasury_account_id,
                'bank_account_id' => $bankAccountId,
                'settlement_date' => $date,
                'currency_code' => $payment->currency_code,
                'gross_amount' => $gross,
                'commission_amount' => $commission,
                'net_amount' => $net,
                'created_at' => $this->clock->now(),
            ]);

            $sourceId = (string) $settlementId;
            $posOut = $this->treasury->post(new PostTreasuryMovementData(
                sourceEffect: new SourceEffectIdentity(
                    $companyId,
                    'treasury_pos_settlement',
                    $sourceId,
                    'treasury.pos_settlement_out',
                ),
                treasuryAccountId: (int) $payment->treasury_account_id,
                postingDate: $date,
                signedAmount: $this->amounts->negate($gross),
                movementType: 'pos_settlement_out',
                accountId: (int) $payment->account_id,
                paymentMethodId: (int) $payment->payment_method_id,
                memo: 'POS settlement brüt çıkış',
            ));
            $bankIn = $this->treasury->post(new PostTreasuryMovementData(
                sourceEffect: new SourceEffectIdentity(
                    $companyId,
                    'treasury_pos_settlement',
                    $sourceId,
                    'treasury.pos_settlement_in',
                ),
                treasuryAccountId: $bankAccountId,
                postingDate: $date,
                signedAmount: $net,
                movementType: 'pos_settlement_in',
                accountId: (int) $payment->account_id,
                paymentMethodId: (int) $payment->payment_method_id,
                memo: 'POS settlement net banka girişi; komisyon '.$commission,
            ));

            $payment->forceFill(['pos_status' => 'settled'])->save();

            $this->audit->record(
                AuditAction::TreasuryPosSettled,
                AuditTargetType::TreasuryPayment,
                $payment->getKey(),
                before: ['pos_status' => 'pending'],
                after: ['pos_status' => 'settled'],
                metadata: [
                    'settlement_id' => $settlementId,
                    'gross_amount' => $gross,
                    'commission_amount' => $commission,
                    'net_amount' => $net,
                    'pos_out_movement_id' => $posOut->getKey(),
                    'bank_in_movement_id' => $bankIn->getKey(),
                ],
            );

            return $settlementId;
        }, 3);
    }

    public function chargebackPos(int $companyId, int $paymentId, string $date): TreasuryPayment
    {
        return DB::transaction(function () use ($companyId, $paymentId, $date): TreasuryPayment {
            $payment = TreasuryPayment::query()
                ->where('company_id', $companyId)
                ->whereKey($paymentId)
                ->lockForUpdate()
                ->firstOrFail();

            if ((string) $payment->pos_status === 'chargeback') {
                return $payment;
            }
            if ((string) $payment->status !== 'finalized' || (string) $payment->pos_status !== 'settled') {
                throw new DomainException('Chargeback yalnız settled POS tahsilatına uygulanabilir.');
            }

            $settlement = DB::table('treasury_pos_settlements')
                ->where('company_id', $companyId)
                ->where('treasury_payment_id', $paymentId)
                ->first();
            if ($settlement === null) {
                throw new DomainException('POS settlement kaydı bulunamadı.');
            }

            $gross = $this->positive((string) $payment->amount);
            $sourceId = (string) $payment->getKey();
            $bankOut = $this->treasury->post(new PostTreasuryMovementData(
                sourceEffect: new SourceEffectIdentity(
                    $companyId,
                    'treasury_pos_chargeback',
                    $sourceId,
                    'treasury.pos_chargeback',
                ),
                treasuryAccountId: (int) $settlement->bank_account_id,
                postingDate: $date,
                signedAmount: $this->amounts->negate($gross),
                movementType: 'pos_chargeback',
                accountId: (int) $payment->account_id,
                paymentMethodId: (int) $payment->payment_method_id,
                memo: 'POS chargeback banka çıkışı',
            ));
            $accountEffect = $this->accounts->post(new PostAccountTransactionData(
                accountId: (int) $payment->account_id,
                postingDate: $date,
                signedAmount: $gross,
                sourceEffect: new SourceEffectIdentity(
                    $companyId,
                    'treasury_pos_chargeback',
                    $sourceId,
                    'account.pos_chargeback',
                ),
                memo: 'POS chargeback; müşteri borcu yeniden açıldı.',
            ));

            $payment->forceFill(['pos_status' => 'chargeback'])->save();

            $this->audit->record(
                AuditAction::TreasuryPosChargeback,
                AuditTargetType::TreasuryPayment,
                $payment->getKey(),
                before: ['pos_status' => 'settled'],
                after: ['pos_status' => 'chargeback'],
                metadata: [
                    'amount' => $gross,
                    'bank_out_movement_id' => $bankOut->getKey(),
                    'account_transaction_id' => $accountEffect->getKey(),
                ],
            );

            return $payment->refresh();
        }, 3);
    }

    public function finalizeManualMovement(int $companyId, int $manualMovementId): TreasuryMovement
    {
        return DB::transaction(function () use ($companyId, $manualMovementId): TreasuryMovement {
            $manual = DB::table('treasury_manual_movements')
                ->where('company_id', $companyId)
                ->where('id', $manualMovementId)
                ->lockForUpdate()
                ->first();
            if ($manual === null) {
                throw new DomainException('Manuel kasa/banka hareketi bulunamadı.');
            }

            $existing = TreasuryMovement::query()
                ->where('company_id', $companyId)
                ->where('source_type', 'treasury_manual_movement')
                ->where('source_id', (string) $manualMovementId)
                ->first();
            if ((string) $manual->status === 'finalized') {
                if (! $existing instanceof TreasuryMovement) {
                    throw new LogicException('Kesinleşmiş manuel hareketin treasury effect kaydı bulunamadı.');
                }

                return $existing;
            }

            $amount = $this->positive((string) $manual->amount);
            $operation = (string) $manual->operation;
            $signedAmount = in_array($operation, ['cash_in', 'bank_in'], true)
                ? $amount
                : $this->amounts->negate($amount);

            $movement = $this->treasury->post(new PostTreasuryMovementData(
                sourceEffect: new SourceEffectIdentity(
                    $companyId,
                    'treasury_manual_movement',
                    (string) $manualMovementId,
                    'treasury.'.$operation,
                ),
                treasuryAccountId: (int) $manual->treasury_account_id,
                postingDate: (string) $manual->movement_date,
                signedAmount: $signedAmount,
                movementType: $operation,
                memo: $manual->note === null ? null : (string) $manual->note,
            ));

            DB::table('treasury_manual_movements')
                ->where('id', $manualMovementId)
                ->update([
                    'status' => 'finalized',
                    'finalized_at' => $this->clock->now(),
                    'updated_at' => $this->clock->now(),
                ]);

            $this->audit->record(
                AuditAction::TreasuryManualMovementFinalized,
                AuditTargetType::TreasuryMovement,
                $movement->getKey(),
                metadata: [
                    'manual_movement_id' => $manualMovementId,
                    'operation' => $operation,
                    'signed_amount' => $signedAmount,
                ],
            );

            return $movement;
        }, 3);
    }

    public function finalizeTransfer(int $companyId, int $transferId): void
    {
        DB::transaction(function () use ($companyId, $transferId): void {
            $transfer = DB::table('treasury_transfers')
                ->where('company_id', $companyId)
                ->where('id', $transferId)
                ->lockForUpdate()
                ->first();
            if ($transfer === null) {
                throw new DomainException('Virman bulunamadı.');
            }
            if ((string) $transfer->status === 'finalized') {
                return;
            }

            $from = TreasuryAccount::query()
                ->where('company_id', $companyId)
                ->whereKey((int) $transfer->from_account_id)
                ->lockForUpdate()
                ->firstOrFail();
            $to = TreasuryAccount::query()
                ->where('company_id', $companyId)
                ->whereKey((int) $transfer->to_account_id)
                ->lockForUpdate()
                ->firstOrFail();
            if ((string) $from->currency_code !== (string) $to->currency_code
                || (string) $from->currency_code !== (string) $transfer->currency_code) {
                throw new DomainException('M10 V1 virmanları aynı para biriminde olmalıdır.');
            }

            $amount = $this->positive((string) $transfer->amount);
            $sourceId = (string) $transferId;
            $out = $this->treasury->post(new PostTreasuryMovementData(
                sourceEffect: new SourceEffectIdentity($companyId, 'treasury_transfer', $sourceId, 'treasury.transfer_out'),
                treasuryAccountId: (int) $from->getKey(),
                postingDate: (string) $transfer->transfer_date,
                signedAmount: $this->amounts->negate($amount),
                movementType: 'transfer_out',
                memo: 'Virman çıkışı',
            ));
            $in = $this->treasury->post(new PostTreasuryMovementData(
                sourceEffect: new SourceEffectIdentity($companyId, 'treasury_transfer', $sourceId, 'treasury.transfer_in'),
                treasuryAccountId: (int) $to->getKey(),
                postingDate: (string) $transfer->transfer_date,
                signedAmount: $amount,
                movementType: 'transfer_in',
                memo: 'Virman girişi',
            ));

            DB::table('treasury_transfers')
                ->where('id', $transferId)
                ->update([
                    'status' => 'finalized',
                    'finalized_at' => $this->clock->now(),
                    'updated_at' => $this->clock->now(),
                ]);

            $this->audit->record(
                AuditAction::TreasuryTransferFinalized,
                AuditTargetType::TreasuryTransfer,
                $transferId,
                before: ['status' => 'draft'],
                after: ['status' => 'finalized'],
                metadata: [
                    'amount' => $amount,
                    'currency_code' => $transfer->currency_code,
                    'out_movement_id' => $out->getKey(),
                    'in_movement_id' => $in->getKey(),
                ],
            );
        }, 3);
    }

    public function finalizeExpense(int $companyId, int $expenseId): void
    {
        DB::transaction(function () use ($companyId, $expenseId): void {
            $expense = DB::table('treasury_expenses')
                ->where('company_id', $companyId)
                ->where('id', $expenseId)
                ->lockForUpdate()
                ->first();
            if ($expense === null) {
                throw new DomainException('Masraf bulunamadı.');
            }
            if ((string) $expense->status === 'finalized') {
                return;
            }

            $amount = $this->positive((string) $expense->amount);
            $movement = $this->treasury->post(new PostTreasuryMovementData(
                sourceEffect: new SourceEffectIdentity($companyId, 'treasury_expense', (string) $expenseId, 'treasury.expense'),
                treasuryAccountId: (int) $expense->treasury_account_id,
                postingDate: (string) $expense->expense_date,
                signedAmount: $this->amounts->negate($amount),
                movementType: 'expense',
                memo: (string) $expense->category,
            ));

            DB::table('treasury_expenses')
                ->where('id', $expenseId)
                ->update([
                    'status' => 'finalized',
                    'finalized_at' => $this->clock->now(),
                    'updated_at' => $this->clock->now(),
                ]);

            $this->audit->record(
                AuditAction::TreasuryExpenseFinalized,
                AuditTargetType::TreasuryExpense,
                $expenseId,
                before: ['status' => 'draft'],
                after: ['status' => 'finalized'],
                metadata: [
                    'amount' => $amount,
                    'category' => $expense->category,
                    'treasury_movement_id' => $movement->getKey(),
                ],
            );
        }, 3);
    }

    public function finalizeCashCount(int $companyId, int $countId): void
    {
        DB::transaction(function () use ($companyId, $countId): void {
            $count = DB::table('treasury_cash_counts')
                ->where('company_id', $companyId)
                ->where('id', $countId)
                ->lockForUpdate()
                ->first();
            if ($count === null) {
                throw new DomainException('Kasa sayımı bulunamadı.');
            }
            if ((string) $count->status === 'finalized') {
                return;
            }

            $account = TreasuryAccount::query()
                ->where('company_id', $companyId)
                ->whereKey((int) $count->treasury_account_id)
                ->lockForUpdate()
                ->firstOrFail();
            if ((string) $account->type !== 'cash') {
                throw new DomainException('Kasa sayımı yalnız cash hesabında yapılabilir.');
            }

            $ledger = $this->zeroAllowed((string) (DB::table('treasury_balances')
                ->where('company_id', $companyId)
                ->where('treasury_account_id', $account->getKey())
                ->value('balance') ?? '0.000000'));
            $countedRow = DB::selectOne(
                <<<'SQL'
SELECT COALESCE(SUM(line_total), 0)::numeric(20,6)::text AS total
FROM treasury_cash_count_lines
WHERE company_id = ? AND treasury_cash_count_id = ?
SQL,
                [$companyId, $countId],
            );
            if ($countedRow === null) {
                throw new LogicException('Kasa sayım toplamı hesaplanamadı.');
            }
            $counted = $this->zeroAllowed((string) $countedRow->total);
            $variance = $this->subtract($counted, $ledger);
            $movementId = null;

            if ($variance !== '0.000000') {
                $movement = $this->treasury->post(new PostTreasuryMovementData(
                    sourceEffect: new SourceEffectIdentity(
                        $companyId,
                        'treasury_cash_count',
                        (string) $countId,
                        'treasury.cash_count_adjustment',
                    ),
                    treasuryAccountId: (int) $account->getKey(),
                    postingDate: (string) $count->count_date,
                    signedAmount: $variance,
                    movementType: 'cash_count_adjustment',
                    memo: 'Kasa sayım farkı',
                ));
                $movementId = $movement->getKey();
            }

            DB::table('treasury_cash_counts')
                ->where('id', $countId)
                ->update([
                    'status' => 'finalized',
                    'ledger_balance' => $ledger,
                    'counted_total' => $counted,
                    'variance' => $variance,
                    'finalized_at' => $this->clock->now(),
                    'updated_at' => $this->clock->now(),
                ]);

            $this->audit->record(
                AuditAction::TreasuryCashCountFinalized,
                AuditTargetType::TreasuryCashCount,
                $countId,
                before: ['status' => 'draft', 'ledger_balance' => $ledger],
                after: ['status' => 'finalized', 'counted_total' => $counted, 'variance' => $variance],
                metadata: ['treasury_movement_id' => $movementId],
            );
        }, 3);
    }

    private function positive(string $value): string
    {
        $value = $this->amounts->normalize($value);
        if (str_starts_with($value, '-')) {
            throw new DomainException('Tutar pozitif olmalıdır.');
        }

        return $value;
    }

    private function nonNegative(string $value): string
    {
        $value = $this->zeroAllowed($value);
        if (str_starts_with($value, '-')) {
            throw new DomainException('Tutar negatif olamaz.');
        }

        return $value;
    }

    private function zeroAllowed(string $value): string
    {
        $value = trim($value);
        if (preg_match('/^-?\d+(?:\.\d{1,6})?$/D', $value) !== 1) {
            throw new DomainException('Tutar en fazla 6 ondalıklı decimal olmalıdır.');
        }

        $negative = str_starts_with($value, '-');
        $unsigned = $negative ? substr($value, 1) : $value;
        [$whole, $fraction] = array_pad(explode('.', $unsigned, 2), 2, '');
        $whole = ltrim($whole, '0');
        $whole = $whole === '' ? '0' : $whole;
        $fraction = str_pad($fraction, 6, '0');
        if ($whole === '0' && $fraction === '000000') {
            return '0.000000';
        }

        return ($negative ? '-' : '').$whole.'.'.$fraction;
    }

    private function subtract(string $left, string $right): string
    {
        $row = DB::selectOne(
            'SELECT (?::numeric(20,6) - ?::numeric(20,6))::numeric(20,6)::text AS value',
            [$left, $right],
        );
        if ($row === null) {
            throw new LogicException('Decimal hesaplama başarısız.');
        }

        return $this->zeroAllowed((string) $row->value);
    }
}

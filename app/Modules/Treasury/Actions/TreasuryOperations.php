<?php

namespace App\Modules\Treasury\Actions;

use App\Foundation\Clock\Clock;
use App\Foundation\Identity\SourceEffectIdentity;
use App\Modules\Accounts\Ledger\AccountAmountNormalizer;
use App\Modules\Accounts\Ledger\AccountTransactionPoster;
use App\Modules\Accounts\Ledger\PostAccountTransactionData;
use App\Modules\Treasury\Ledger\PostTreasuryMovementData;
use App\Modules\Treasury\Ledger\TreasuryMovementPoster;
use App\Modules\Treasury\Models\TreasuryAccount;
use App\Modules\Treasury\Models\TreasuryMovement;
use App\Modules\Treasury\Models\TreasuryPayment;
use DomainException;
use Illuminate\Support\Facades\DB;

final readonly class TreasuryOperations
{
    public function __construct(
        private TreasuryMovementPoster $treasury,
        private AccountTransactionPoster $accounts,
        private AccountAmountNormalizer $amounts,
        private Clock $clock,
    ) {}

    public function finalizePayment(int $companyId, int $paymentId): TreasuryPayment
    {
        return DB::transaction(function () use ($companyId, $paymentId): TreasuryPayment {
            $payment = TreasuryPayment::query()->where('company_id', $companyId)->whereKey($paymentId)->lockForUpdate()->firstOrFail();
            if ((string) $payment->status !== 'draft') return $payment;

            $amount = $this->positive((string) $payment->amount);
            $collection = (string) $payment->direction === 'collection';
            $treasuryAmount = $collection ? $amount : $this->amounts->negate($amount);
            $accountAmount = $this->amounts->negate($treasuryAmount);
            $sourceId = (string) $payment->getKey();
            $kind = (string) $payment->payment_kind;

            $this->treasury->post(new PostTreasuryMovementData(
                new SourceEffectIdentity($companyId, 'treasury_payment', $sourceId, $collection ? 'treasury.collection' : 'treasury.payment'),
                (int) $payment->treasury_account_id,
                $payment->payment_date->format('Y-m-d'),
                $treasuryAmount,
                $collection && in_array($kind, ['pos', 'virtual_pos'], true) ? 'pos_pending' : ($collection ? 'collection' : 'payment'),
                (int) $payment->account_id,
                (int) $payment->payment_method_id,
                $payment->note === null ? null : (string) $payment->note,
            ));

            $this->accounts->post(new PostAccountTransactionData(
                accountId: (int) $payment->account_id,
                postingDate: $payment->payment_date->format('Y-m-d'),
                signedAmount: $accountAmount,
                sourceEffect: new SourceEffectIdentity($companyId, 'treasury_payment', $sourceId, $collection ? 'account.collection' : 'account.payment'),
                memo: $payment->note === null ? null : (string) $payment->note,
            ));

            $payment->forceFill(['status' => 'finalized', 'finalized_at' => $this->clock->now()])->save();
            return $payment->refresh();
        }, 3);
    }

    public function reversePayment(int $companyId, int $paymentId): TreasuryPayment
    {
        return DB::transaction(function () use ($companyId, $paymentId): TreasuryPayment {
            $payment = TreasuryPayment::query()->where('company_id', $companyId)->whereKey($paymentId)->lockForUpdate()->firstOrFail();
            if ((string) $payment->status === 'reversed') return $payment;
            if ((string) $payment->status !== 'finalized') throw new DomainException('Yalnız kesinleşmiş tahsilat/ödeme ters çevrilebilir.');
            if (in_array((string) $payment->payment_kind, ['pos', 'virtual_pos'], true) && (string) $payment->pos_status === 'settled') {
                throw new DomainException('Settled POS işlemi reversal yerine chargeback ile kapatılmalıdır.');
            }

            $originalTreasury = TreasuryMovement::query()
                ->where('company_id', $companyId)->where('source_type', 'treasury_payment')->where('source_id', (string) $payment->getKey())->firstOrFail();
            $originalAccount = DB::table('account_transactions')
                ->where('company_id', $companyId)->where('source_type', 'treasury_payment')->where('source_id', (string) $payment->getKey())->first();
            if ($originalAccount === null) throw new DomainException('Tahsilat/ödeme cari kaydı bulunamadı.');

            $treasuryAmount = $this->amounts->negate((string) $originalTreasury->signed_amount);
            $accountAmount = $this->amounts->negate((string) $originalAccount->signed_amount);
            $sourceId = (string) $payment->getKey();
            $collection = (string) $payment->direction === 'collection';

            $this->treasury->post(new PostTreasuryMovementData(
                new SourceEffectIdentity($companyId, 'treasury_payment_reversal', $sourceId, $collection ? 'treasury.collection_reversal' : 'treasury.payment_reversal'),
                (int) $payment->treasury_account_id,
                $payment->payment_date->format('Y-m-d'),
                $treasuryAmount,
                in_array((string) $payment->payment_kind, ['pos','virtual_pos'], true) ? 'pos_reversal' : ($collection ? 'payment' : 'collection'),
                (int) $payment->account_id,
                (int) $payment->payment_method_id,
                'Tahsilat/ödeme ters kaydı',
                (int) $originalTreasury->getKey(),
            ));
            $this->accounts->post(new PostAccountTransactionData(
                accountId: (int) $payment->account_id,
                postingDate: $payment->payment_date->format('Y-m-d'),
                signedAmount: $accountAmount,
                sourceEffect: new SourceEffectIdentity($companyId, 'treasury_payment_reversal', $sourceId, $collection ? 'account.collection_reversal' : 'account.payment_reversal'),
                memo: 'Tahsilat/ödeme ters kaydı',
                reversalOfTransactionId: (int) $originalAccount->id,
            ));

            $attributes = ['status' => 'reversed', 'reversed_at' => $this->clock->now()];
            if (in_array((string) $payment->payment_kind, ['pos','virtual_pos'], true)) $attributes['pos_status'] = 'reversed';
            $payment->forceFill($attributes)->save();
            return $payment->refresh();
        }, 3);
    }

    public function settlePos(int $companyId, int $paymentId, int $bankAccountId, string $date, string $commission): int
    {
        return DB::transaction(function () use ($companyId, $paymentId, $bankAccountId, $date, $commission): int {
            $payment = TreasuryPayment::query()->where('company_id', $companyId)->whereKey($paymentId)->lockForUpdate()->firstOrFail();
            if ((string) $payment->status !== 'finalized' || (string) $payment->pos_status !== 'pending') throw new DomainException('Yalnız pending POS tahsilatı settlement yapılabilir.');
            if (! in_array((string) $payment->payment_kind, ['pos','virtual_pos'], true)) throw new DomainException('İşlem POS/Sanal POS değildir.');

            $bank = TreasuryAccount::query()->where('company_id', $companyId)->whereKey($bankAccountId)->lockForUpdate()->firstOrFail();
            if ((string) $bank->type !== 'bank' || ! (bool) $bank->is_active || (string) $bank->currency_code !== (string) $payment->currency_code) {
                throw new DomainException('Settlement aynı para biriminde aktif banka hesabına yapılmalıdır.');
            }
            $gross = $this->positive((string) $payment->amount);
            $commission = $this->nonNegative($commission);
            $net = $this->subtract($gross, $commission);
            if (str_starts_with($net, '-') || $net === '0.000000') throw new DomainException('POS komisyonu brüt tahsilattan küçük olmalıdır.');

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
            $this->treasury->post(new PostTreasuryMovementData(
                new SourceEffectIdentity($companyId, 'treasury_pos_settlement', $sourceId, 'treasury.pos_settlement_out'),
                (int) $payment->treasury_account_id, $date, $this->amounts->negate($gross), 'pos_settlement_out',
                (int) $payment->account_id, (int) $payment->payment_method_id, 'POS settlement brüt çıkış',
            ));
            $this->treasury->post(new PostTreasuryMovementData(
                new SourceEffectIdentity($companyId, 'treasury_pos_settlement', $sourceId, 'treasury.pos_settlement_in'),
                $bankAccountId, $date, $net, 'pos_settlement_in',
                (int) $payment->account_id, (int) $payment->payment_method_id, 'POS settlement net banka girişi; komisyon '.$commission,
            ));
            $payment->forceFill(['pos_status' => 'settled'])->save();
            return $settlementId;
        }, 3);
    }

    public function chargebackPos(int $companyId, int $paymentId, string $date): TreasuryPayment
    {
        return DB::transaction(function () use ($companyId, $paymentId, $date): TreasuryPayment {
            $payment = TreasuryPayment::query()->where('company_id', $companyId)->whereKey($paymentId)->lockForUpdate()->firstOrFail();
            if ((string) $payment->status !== 'finalized' || (string) $payment->pos_status !== 'settled') throw new DomainException('Chargeback yalnız settled POS tahsilatına uygulanabilir.');
            $settlement = DB::table('treasury_pos_settlements')->where('company_id', $companyId)->where('treasury_payment_id', $paymentId)->first();
            if ($settlement === null) throw new DomainException('POS settlement kaydı bulunamadı.');
            $gross = $this->positive((string) $payment->amount);
            $sourceId = (string) $payment->getKey();
            $this->treasury->post(new PostTreasuryMovementData(
                new SourceEffectIdentity($companyId, 'treasury_pos_chargeback', $sourceId, 'treasury.pos_chargeback'),
                (int) $settlement->bank_account_id, $date, $this->amounts->negate($gross), 'pos_chargeback',
                (int) $payment->account_id, (int) $payment->payment_method_id, 'POS chargeback banka çıkışı',
            ));
            $this->accounts->post(new PostAccountTransactionData(
                accountId: (int) $payment->account_id, postingDate: $date, signedAmount: $gross,
                sourceEffect: new SourceEffectIdentity($companyId, 'treasury_pos_chargeback', $sourceId, 'account.pos_chargeback'),
                memo: 'POS chargeback; müşteri borcu yeniden açıldı.',
            ));
            $payment->forceFill(['pos_status' => 'chargeback'])->save();
            return $payment->refresh();
        }, 3);
    }

    public function finalizeTransfer(int $companyId, int $transferId): void
    {
        DB::transaction(function () use ($companyId, $transferId): void {
            $transfer = DB::table('treasury_transfers')->where('company_id', $companyId)->where('id', $transferId)->lockForUpdate()->first();
            if ($transfer === null) throw new DomainException('Virman bulunamadı.');
            if ((string) $transfer->status === 'finalized') return;
            $from = TreasuryAccount::query()->where('company_id', $companyId)->whereKey((int) $transfer->from_account_id)->lockForUpdate()->firstOrFail();
            $to = TreasuryAccount::query()->where('company_id', $companyId)->whereKey((int) $transfer->to_account_id)->lockForUpdate()->firstOrFail();
            if ((string) $from->currency_code !== (string) $to->currency_code || (string) $from->currency_code !== (string) $transfer->currency_code) throw new DomainException('M10 V1 virmanları aynı para biriminde olmalıdır.');
            $amount = $this->positive((string) $transfer->amount);
            $sourceId = (string) $transferId;
            $this->treasury->post(new PostTreasuryMovementData(new SourceEffectIdentity($companyId, 'treasury_transfer', $sourceId, 'treasury.transfer_out'), (int) $from->getKey(), (string) $transfer->transfer_date, $this->amounts->negate($amount), 'transfer_out', memo: 'Virman çıkışı'));
            $this->treasury->post(new PostTreasuryMovementData(new SourceEffectIdentity($companyId, 'treasury_transfer', $sourceId, 'treasury.transfer_in'), (int) $to->getKey(), (string) $transfer->transfer_date, $amount, 'transfer_in', memo: 'Virman girişi'));
            DB::table('treasury_transfers')->where('id', $transferId)->update(['status' => 'finalized', 'finalized_at' => $this->clock->now(), 'updated_at' => $this->clock->now()]);
        }, 3);
    }

    public function finalizeExpense(int $companyId, int $expenseId): void
    {
        DB::transaction(function () use ($companyId, $expenseId): void {
            $expense = DB::table('treasury_expenses')->where('company_id', $companyId)->where('id', $expenseId)->lockForUpdate()->first();
            if ($expense === null) throw new DomainException('Masraf bulunamadı.');
            if ((string) $expense->status === 'finalized') return;
            $amount = $this->positive((string) $expense->amount);
            $this->treasury->post(new PostTreasuryMovementData(
                new SourceEffectIdentity($companyId, 'treasury_expense', (string) $expenseId, 'treasury.expense'),
                (int) $expense->treasury_account_id, (string) $expense->expense_date, $this->amounts->negate($amount), 'expense', memo: (string) $expense->category,
            ));
            DB::table('treasury_expenses')->where('id', $expenseId)->update(['status' => 'finalized', 'finalized_at' => $this->clock->now(), 'updated_at' => $this->clock->now()]);
        }, 3);
    }

    public function finalizeCashCount(int $companyId, int $countId): void
    {
        DB::transaction(function () use ($companyId, $countId): void {
            $count = DB::table('treasury_cash_counts')->where('company_id', $companyId)->where('id', $countId)->lockForUpdate()->first();
            if ($count === null) throw new DomainException('Kasa sayımı bulunamadı.');
            if ((string) $count->status === 'finalized') return;
            $account = TreasuryAccount::query()->where('company_id', $companyId)->whereKey((int) $count->treasury_account_id)->lockForUpdate()->firstOrFail();
            if ((string) $account->type !== 'cash') throw new DomainException('Kasa sayımı yalnız cash hesabında yapılabilir.');
            $ledger = (string) (DB::table('treasury_balances')->where('company_id', $companyId)->where('treasury_account_id', $account->getKey())->value('balance') ?? '0.000000');
            $counted = (string) (DB::table('treasury_cash_count_lines')->where('company_id', $companyId)->where('treasury_cash_count_id', $countId)->sum('line_total') ?? '0.000000');
            $ledger = $this->zeroAllowed($ledger);
            $counted = $this->zeroAllowed($counted);
            $variance = $this->subtract($counted, $ledger);
            if ($variance !== '0.000000') {
                $this->treasury->post(new PostTreasuryMovementData(
                    new SourceEffectIdentity($companyId, 'treasury_cash_count', (string) $countId, 'treasury.cash_count_adjustment'),
                    (int) $account->getKey(), (string) $count->count_date, $variance, 'cash_count_adjustment', memo: 'Kasa sayım farkı',
                ));
            }
            DB::table('treasury_cash_counts')->where('id', $countId)->update([
                'status' => 'finalized', 'ledger_balance' => $ledger, 'counted_total' => $counted, 'variance' => $variance,
                'finalized_at' => $this->clock->now(), 'updated_at' => $this->clock->now(),
            ]);
        }, 3);
    }

    private function positive(string $value): string
    {
        $value = $this->amounts->normalize($value);
        if (str_starts_with($value, '-')) throw new DomainException('Tutar pozitif olmalıdır.');
        return $value;
    }

    private function nonNegative(string $value): string
    {
        $value = $this->zeroAllowed($value);
        if (str_starts_with($value, '-')) throw new DomainException('Tutar negatif olamaz.');
        return $value;
    }

    private function zeroAllowed(string $value): string
    {
        $value = trim($value);
        if (preg_match('/^-?\d+(?:\.\d{1,6})?$/D', $value) !== 1) throw new DomainException('Tutar en fazla 6 ondalıklı decimal olmalıdır.');
        $negative = str_starts_with($value, '-');
        $unsigned = $negative ? substr($value, 1) : $value;
        [$whole, $fraction] = array_pad(explode('.', $unsigned, 2), 2, '');
        $whole = ltrim($whole, '0'); $whole = $whole === '' ? '0' : $whole;
        $fraction = str_pad($fraction, 6, '0');
        if ($whole === '0' && $fraction === '000000') return '0.000000';
        return ($negative ? '-' : '').$whole.'.'.$fraction;
    }

    private function subtract(string $left, string $right): string
    {
        $row = DB::selectOne('SELECT (?::numeric(20,6) - ?::numeric(20,6))::text AS value', [$left, $right]);
        if ($row === null) throw new DomainException('Decimal hesaplama başarısız.');
        return $this->zeroAllowed((string) $row->value);
    }
}

<?php

namespace App\Modules\Instruments\Actions;

use App\Foundation\Clock\Clock;
use App\Foundation\Identity\SourceEffectIdentity;
use App\Modules\Accounts\Enums\AccountType;
use App\Modules\Accounts\Ledger\AccountAmountNormalizer;
use App\Modules\Accounts\Ledger\AccountTransactionPoster;
use App\Modules\Accounts\Ledger\PostAccountTransactionData;
use App\Modules\Accounts\Models\Account;
use App\Modules\Accounts\Models\AccountTransaction;
use App\Modules\Instruments\Models\Instrument;
use App\Modules\Instruments\Models\InstrumentEvent;
use App\Modules\Treasury\Ledger\PostTreasuryMovementData;
use App\Modules\Treasury\Ledger\TreasuryMovementPoster;
use App\Modules\Treasury\Models\TreasuryAccount;
use App\Modules\Treasury\Models\TreasuryMovement;
use DateTimeImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

final readonly class InstrumentOperations
{
    public function __construct(
        private AccountTransactionPoster $accountPoster,
        private TreasuryMovementPoster $treasuryPoster,
        private AccountAmountNormalizer $amounts,
        private Clock $clock,
    ) {}

    public function register(
        int $companyId,
        int $accountId,
        string $direction,
        string $kind,
        string $documentNo,
        string $amount,
        string $currencyCode,
        string $deliveryDate,
        string $dueDate,
        ?string $issueDate = null,
        ?string $bankName = null,
        ?string $branchName = null,
        ?string $drawerOrMaker = null,
        ?string $note = null,
    ): Instrument {
        $direction = $this->direction($direction);
        $kind = $this->kind($kind);
        $documentNo = $this->requiredText($documentNo, 120, 'Belge numarası');
        $amount = $this->positiveAmount($amount);
        $currencyCode = $this->currency($currencyCode);
        $this->date($deliveryDate, 'Teslim tarihi');
        $this->date($dueDate, 'Vade tarihi');
        if ($issueDate !== null) {
            $this->date($issueDate, 'Düzenleme tarihi');
            if ($issueDate > $dueDate) {
                throw new InvalidArgumentException('Vade tarihi düzenleme tarihinden önce olamaz.');
            }
        }

        return DB::transaction(function () use ($companyId, $accountId, $direction, $kind, $documentNo, $amount, $currencyCode, $deliveryDate, $dueDate, $issueDate, $bankName, $branchName, $drawerOrMaker, $note): Instrument {
            $account = $this->commercialAccount($companyId, $accountId, $direction, $currencyCode);
            $instrument = Instrument::query()->create([
                'company_id' => $companyId, 'account_id' => $accountId, 'direction' => $direction, 'kind' => $kind,
                'status' => 'draft', 'document_no' => $documentNo, 'amount' => $amount, 'currency_code' => $currencyCode,
                'issue_date' => $issueDate, 'delivery_date' => $deliveryDate, 'due_date' => $dueDate,
                'bank_name' => $this->nullableText($bankName, 160), 'branch_name' => $this->nullableText($branchName, 120),
                'drawer_or_maker' => $this->nullableText($drawerOrMaker, 200), 'note' => $this->nullableText($note, 5000),
            ]);
            $instrumentId = $this->id($instrument);
            $posting = $this->accountPoster->post(new PostAccountTransactionData(
                accountId: $accountId,
                postingDate: $deliveryDate,
                signedAmount: $direction === 'received' ? $this->amounts->negate($amount) : $amount,
                sourceEffect: new SourceEffectIdentity($companyId, 'instrument', (string) $instrumentId, 'account.instrument_delivery'),
                memo: $this->memo($instrument),
            ));
            $initialStatus = $direction === 'received' ? 'portfolio' : 'issued';
            $instrument->update([
                'status' => $initialStatus,
                'current_holder_type' => $direction === 'received' ? 'company' : 'account',
                'current_holder_account_id' => $direction === 'received' ? null : $accountId,
                'delivery_account_transaction_id' => $this->id($posting),
                'registered_at' => $this->clock->now(),
            ]);
            $this->event($instrument, 'registered', 'draft', $initialStatus, $deliveryDate, $account, null, $posting, null);

            return $instrument->refresh();
        }, 3);
    }

    public function sendToBank(int $companyId, int $instrumentId, int $treasuryAccountId, string $eventDate): Instrument
    {
        $this->date($eventDate, 'Bankaya gönderim tarihi');

        return DB::transaction(function () use ($companyId, $instrumentId, $treasuryAccountId, $eventDate): Instrument {
            $instrument = $this->lockInstrument($companyId, $instrumentId);
            if ($instrument->status === 'bank_collection' && (int) $instrument->current_treasury_account_id === $treasuryAccountId) {
                return $instrument;
            }
            $this->requireState($instrument, 'received', ['portfolio']);
            $bank = $this->bankAccount($companyId, $treasuryAccountId, (string) $instrument->currency_code);
            $instrument->update(['status' => 'bank_collection', 'current_holder_type' => 'bank', 'current_holder_account_id' => null, 'current_treasury_account_id' => $this->id($bank)]);
            $this->event($instrument, 'sent_to_bank', 'portfolio', 'bank_collection', $eventDate, null, $bank, null, null);

            return $instrument->refresh();
        }, 3);
    }

    public function recallFromBank(int $companyId, int $instrumentId, string $eventDate): Instrument
    {
        $this->date($eventDate, 'Bankadan geri alma tarihi');

        return DB::transaction(function () use ($companyId, $instrumentId, $eventDate): Instrument {
            $instrument = $this->lockInstrument($companyId, $instrumentId);
            if ($instrument->status === 'portfolio') {
                return $instrument;
            }
            $this->requireState($instrument, 'received', ['bank_collection']);
            $bankId = (int) $instrument->current_treasury_account_id;
            $bank = TreasuryAccount::query()->where('company_id', $companyId)->findOrFail($bankId);
            $instrument->update(['status' => 'portfolio', 'current_holder_type' => 'company', 'current_holder_account_id' => null, 'current_treasury_account_id' => null]);
            $this->event($instrument, 'recalled_from_bank', 'bank_collection', 'portfolio', $eventDate, null, $bank, null, null);

            return $instrument->refresh();
        }, 3);
    }

    public function endorse(int $companyId, int $instrumentId, int $supplierAccountId, string $eventDate): Instrument
    {
        $this->date($eventDate, 'Ciro tarihi');

        return DB::transaction(function () use ($companyId, $instrumentId, $supplierAccountId, $eventDate): Instrument {
            $instrument = $this->lockInstrument($companyId, $instrumentId);
            if ($instrument->status === 'endorsed' && (int) $instrument->endorsed_to_account_id === $supplierAccountId) {
                return $instrument;
            }
            $this->requireState($instrument, 'received', ['portfolio']);
            $supplier = $this->supplierAccount($companyId, $supplierAccountId, (string) $instrument->currency_code);
            $posting = $this->accountPoster->post(new PostAccountTransactionData(
                accountId: $supplierAccountId, postingDate: $eventDate, signedAmount: (string) $instrument->amount,
                sourceEffect: new SourceEffectIdentity($companyId, 'instrument', (string) $instrumentId, 'account.instrument_endorsement'),
                memo: 'Çek/senet ciro: '.(string) $instrument->document_no,
            ));
            $instrument->update([
                'status' => 'endorsed', 'current_holder_type' => 'account', 'current_holder_account_id' => $supplierAccountId,
                'current_treasury_account_id' => null, 'endorsed_to_account_id' => $supplierAccountId,
                'endorsement_account_transaction_id' => $this->id($posting),
            ]);
            $this->event($instrument, 'endorsed', 'portfolio', 'endorsed', $eventDate, $supplier, null, $posting, null);

            return $instrument->refresh();
        }, 3);
    }

    public function settle(int $companyId, int $instrumentId, int $treasuryAccountId, string $eventDate): Instrument
    {
        $this->date($eventDate, 'Tahsil/ödeme tarihi');

        return DB::transaction(function () use ($companyId, $instrumentId, $treasuryAccountId, $eventDate): Instrument {
            $instrument = $this->lockInstrument($companyId, $instrumentId);
            if (in_array($instrument->status, ['collected', 'settled'], true)) {
                if ((int) $instrument->settlement_treasury_account_id !== $treasuryAccountId) {
                    throw new DomainException('Çek/senet farklı banka hesabında daha önce kapatılmış.');
                }

                return $instrument;
            }
            $direction = (string) $instrument->direction;
            if ($direction === 'received') {
                $this->requireState($instrument, 'received', ['bank_collection']);
                if ((int) $instrument->current_treasury_account_id !== $treasuryAccountId) {
                    throw new DomainException('Tahsil hesabı bankaya gönderilen hesapla aynı olmalıdır.');
                }
            } else {
                $this->requireState($instrument, 'issued', ['issued']);
            }
            $bank = $this->bankAccount($companyId, $treasuryAccountId, (string) $instrument->currency_code);
            $movement = $this->treasuryPoster->post(new PostTreasuryMovementData(
                sourceEffect: new SourceEffectIdentity($companyId, 'instrument', (string) $instrumentId, 'treasury.instrument_settlement'),
                treasuryAccountId: $treasuryAccountId, postingDate: $eventDate,
                signedAmount: $direction === 'received' ? (string) $instrument->amount : $this->amounts->negate((string) $instrument->amount),
                movementType: $direction === 'received' ? 'collection' : 'payment', accountId: (int) $instrument->account_id,
                memo: 'Çek/senet banka kapanışı: '.(string) $instrument->document_no,
            ));
            $targetStatus = $direction === 'received' ? 'collected' : 'settled';
            $fromStatus = (string) $instrument->status;
            $instrument->update([
                'status' => $targetStatus, 'current_holder_type' => 'settled', 'current_holder_account_id' => null,
                'current_treasury_account_id' => null, 'settlement_treasury_account_id' => $treasuryAccountId,
                'settlement_treasury_movement_id' => $this->id($movement), 'settled_at' => $this->clock->now(),
            ]);
            $this->event($instrument, 'settled', $fromStatus, $targetStatus, $eventDate, null, $bank, null, $movement);

            return $instrument->refresh();
        }, 3);
    }

    public function dishonor(int $companyId, int $instrumentId, string $eventDate): Instrument
    {
        return $this->reverseOpenInstrument($companyId, $instrumentId, $eventDate, 'dishonored');
    }

    public function returnToCounterparty(int $companyId, int $instrumentId, string $eventDate): Instrument
    {
        return $this->reverseOpenInstrument($companyId, $instrumentId, $eventDate, 'returned');
    }

    public function cancel(int $companyId, int $instrumentId, string $eventDate): Instrument
    {
        return $this->reverseOpenInstrument($companyId, $instrumentId, $eventDate, 'cancelled');
    }

    private function reverseOpenInstrument(int $companyId, int $instrumentId, string $eventDate, string $outcome): Instrument
    {
        $this->date($eventDate, 'Ters kayıt tarihi');

        return DB::transaction(function () use ($companyId, $instrumentId, $eventDate, $outcome): Instrument {
            $instrument = $this->lockInstrument($companyId, $instrumentId);
            $direction = (string) $instrument->direction;
            $terminal = $outcome === 'dishonored' && $direction === 'issued' ? 'unpaid' : $outcome;
            if ($instrument->status === $terminal) {
                return $instrument;
            }
            $allowed = $direction === 'received' ? ($outcome === 'returned' ? ['portfolio'] : ['portfolio', 'bank_collection', 'endorsed']) : ($outcome === 'returned' ? [] : ['issued']);
            if ($allowed === []) {
                throw new DomainException('Verilen çek/senet karşı tarafa iade operasyonunu desteklemez.');
            }
            $this->requireState($instrument, $direction, $allowed);
            $delivery = AccountTransaction::query()->where('company_id', $companyId)->findOrFail((int) $instrument->delivery_account_transaction_id);
            $deliveryReversal = $this->accountPoster->post(new PostAccountTransactionData(
                accountId: (int) $instrument->account_id, postingDate: $eventDate,
                signedAmount: $this->amounts->negate((string) $delivery->signed_amount),
                sourceEffect: new SourceEffectIdentity($companyId, 'instrument', (string) $instrumentId, 'account.instrument_delivery_reversal'),
                memo: 'Çek/senet teslim etkisi ters kayıt: '.(string) $instrument->document_no,
                reversalOfTransactionId: $this->id($delivery),
            ));
            $endorsementReversal = null;
            if ($instrument->endorsement_account_transaction_id !== null) {
                $endorsement = AccountTransaction::query()->where('company_id', $companyId)->findOrFail((int) $instrument->endorsement_account_transaction_id);
                $endorsementReversal = $this->accountPoster->post(new PostAccountTransactionData(
                    accountId: (int) $instrument->endorsed_to_account_id, postingDate: $eventDate,
                    signedAmount: $this->amounts->negate((string) $endorsement->signed_amount),
                    sourceEffect: new SourceEffectIdentity($companyId, 'instrument', (string) $instrumentId, 'account.instrument_endorsement_reversal'),
                    memo: 'Çek/senet ciro etkisi ters kayıt: '.(string) $instrument->document_no,
                    reversalOfTransactionId: $this->id($endorsement),
                ));
            }
            $fromStatus = (string) $instrument->status;
            $holderType = match ($terminal) {
                'dishonored' => 'company', 'unpaid', 'returned' => 'account', default => 'none'
            };
            $holderAccountId = in_array($terminal, ['unpaid', 'returned'], true) ? (int) $instrument->account_id : null;
            $instrument->update([
                'status' => $terminal, 'current_holder_type' => $holderType, 'current_holder_account_id' => $holderAccountId,
                'current_treasury_account_id' => null, 'delivery_reversal_account_transaction_id' => $this->id($deliveryReversal),
                'endorsement_reversal_account_transaction_id' => $endorsementReversal === null ? null : $this->id($endorsementReversal),
                'reversed_at' => $this->clock->now(),
            ]);
            $eventType = match ($terminal) {
                'returned' => 'returned', 'cancelled' => 'cancelled', default => 'dishonored'
            };
            $counterparty = in_array($terminal, ['returned', 'unpaid'], true) ? $instrument->account()->first() : null;
            $this->event($instrument, $eventType, $fromStatus, $terminal, $eventDate, $counterparty, null, $deliveryReversal, null,
                $endorsementReversal === null ? null : ['endorsement_reversal_account_transaction_id' => $this->id($endorsementReversal)]);

            return $instrument->refresh();
        }, 3);
    }

    private function commercialAccount(int $companyId, int $accountId, string $direction, string $currency): Account
    {
        $account = Account::query()->where('company_id', $companyId)->whereKey($accountId)->sharedLock()->firstOrFail();
        if (! $account->isActive()) {
            throw new DomainException('Çek/senet aktif cari gerektirir.');
        }
        if ((string) $account->book_currency_code !== $currency) {
            throw new DomainException('Çek/senet para birimi cari defter para birimiyle aynı olmalıdır.');
        }
        $allowed = $direction === 'received' ? [AccountType::Customer, AccountType::Mixed] : [AccountType::Supplier, AccountType::Mixed];
        if (! in_array($account->typeEnum(), $allowed, true)) {
            throw new DomainException($direction === 'received' ? 'Alınan çek/senet müşteri carisi gerektirir.' : 'Verilen çek/senet tedarikçi carisi gerektirir.');
        }

        return $account;
    }

    private function supplierAccount(int $companyId, int $accountId, string $currency): Account
    {
        $account = Account::query()->where('company_id', $companyId)->whereKey($accountId)->sharedLock()->firstOrFail();
        if (! $account->isActive() || ! in_array($account->typeEnum(), [AccountType::Supplier, AccountType::Mixed], true)) {
            throw new DomainException('Ciro aktif tedarikçi carisi gerektirir.');
        }
        if ((string) $account->book_currency_code !== $currency) {
            throw new DomainException('Ciro hedef carisi aynı para biriminde olmalıdır.');
        }

        return $account;
    }

    private function bankAccount(int $companyId, int $treasuryAccountId, string $currency): TreasuryAccount
    {
        $bank = TreasuryAccount::query()->where('company_id', $companyId)->whereKey($treasuryAccountId)->lockForUpdate()->firstOrFail();
        if ((string) $bank->type !== 'bank' || ! (bool) $bank->is_active) {
            throw new DomainException('Çek/senet kapanışı aktif banka hesabı gerektirir.');
        }
        if ((string) $bank->currency_code !== $currency) {
            throw new DomainException('Banka hesabı çek/senet ile aynı para biriminde olmalıdır.');
        }

        return $bank;
    }

    private function lockInstrument(int $companyId, int $instrumentId): Instrument
    {
        return Instrument::query()->where('company_id', $companyId)->whereKey($instrumentId)->lockForUpdate()->firstOrFail();
    }

    /** @param list<string> $allowedStatuses */
    private function requireState(Instrument $instrument, string $direction, array $allowedStatuses): void
    {
        if ((string) $instrument->direction !== $direction || ! in_array((string) $instrument->status, $allowedStatuses, true)) {
            throw new DomainException('Çek/senet bu lifecycle operasyonu için uygun durumda değil.');
        }
    }

    /** @param array<string, int>|null $metadata */
    private function event(Instrument $instrument, string $eventType, string $fromStatus, string $toStatus, string $eventDate, ?Account $counterparty, ?TreasuryAccount $treasuryAccount, ?AccountTransaction $accountTransaction, ?TreasuryMovement $treasuryMovement, ?array $metadata = null): InstrumentEvent
    {
        return InstrumentEvent::query()->create([
            'company_id' => (int) $instrument->company_id, 'instrument_id' => $this->id($instrument), 'event_type' => $eventType,
            'event_date' => $eventDate, 'from_status' => $fromStatus, 'to_status' => $toStatus,
            'counterparty_account_id' => $counterparty === null ? null : $this->id($counterparty),
            'treasury_account_id' => $treasuryAccount === null ? null : $this->id($treasuryAccount),
            'account_transaction_id' => $accountTransaction === null ? null : $this->id($accountTransaction),
            'treasury_movement_id' => $treasuryMovement === null ? null : $this->id($treasuryMovement),
            'metadata' => $metadata, 'created_at' => $this->clock->now(),
        ]);
    }

    private function direction(string $direction): string
    {
        $direction = trim($direction);
        if (! in_array($direction, ['received', 'issued'], true)) {
            throw new InvalidArgumentException('Çek/senet yönü received veya issued olmalıdır.');
        }

        return $direction;
    }

    private function kind(string $kind): string
    {
        $kind = trim($kind);
        if (! in_array($kind, ['cheque', 'promissory_note'], true)) {
            throw new InvalidArgumentException('Belge türü cheque veya promissory_note olmalıdır.');
        }

        return $kind;
    }

    private function currency(string $currency): string
    {
        $currency = strtoupper(trim($currency));
        if (preg_match('/^[A-Z]{3}$/D', $currency) !== 1) {
            throw new InvalidArgumentException('Para birimi ISO-4217 üç harf kodu olmalıdır.');
        }

        return $currency;
    }

    private function positiveAmount(string $amount): string
    {
        $amount = $this->amounts->normalize($amount);
        if (str_starts_with($amount, '-')) {
            throw new InvalidArgumentException('Çek/senet tutarı pozitif olmalıdır.');
        }

        return $amount;
    }

    private function date(string $date, string $label): void
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (! $parsed instanceof DateTimeImmutable || $parsed->format('Y-m-d') !== $date) {
            throw new InvalidArgumentException($label.' Y-m-d formatında geçerli bir tarih olmalıdır.');
        }
    }

    private function requiredText(string $value, int $max, string $label): string
    {
        $value = trim($value);
        if ($value === '' || mb_strlen($value) > $max) {
            throw new InvalidArgumentException($label.' boş olamaz ve en fazla '.$max.' karakter olabilir.');
        }

        return $value;
    }

    private function nullableText(?string $value, int $max): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        } $value = trim($value);
        if (mb_strlen($value) > $max) {
            throw new InvalidArgumentException('Metin alanı en fazla '.$max.' karakter olabilir.');
        }

        return $value;
    }

    private function memo(Instrument $instrument): string
    {
        return ($instrument->direction === 'received' ? 'Alınan' : 'Verilen').' '.($instrument->kind === 'cheque' ? 'çek' : 'senet').': '.(string) $instrument->document_no;
    }

    private function id(object $model): int
    {
        if (! method_exists($model, 'getKey')) {
            throw new LogicException('Persisted model key is required.');
        } $id = $model->getKey();

        return is_int($id) ? $id : throw new LogicException('Persisted model did not return an integer key.');
    }
}

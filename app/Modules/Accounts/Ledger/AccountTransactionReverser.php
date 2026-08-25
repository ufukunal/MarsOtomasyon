<?php

namespace App\Modules\Accounts\Ledger;

use App\Foundation\Identity\SourceEffectIdentity;
use App\Modules\Accounts\Models\AccountTransaction;
use DomainException;
use Illuminate\Support\Facades\DB;
use LogicException;

final readonly class AccountTransactionReverser
{
    public function __construct(
        private AccountTransactionPoster $poster,
        private AccountAmountNormalizer $amounts,
    ) {}

    public function reverse(
        int $originalTransactionId,
        string $postingDate,
        SourceEffectIdentity $sourceEffect,
        ?string $memo = null,
    ): AccountTransaction {
        if (DB::connection()->transactionLevel() < 1) {
            throw new LogicException('Cari hareket reversal aynı business transaction içinde çalışmalıdır.');
        }

        $original = AccountTransaction::query()
            ->where('company_id', $sourceEffect->companyId)
            ->whereKey($originalTransactionId)
            ->sharedLock()
            ->first();

        if (! $original instanceof AccountTransaction) {
            throw new DomainException('Ters kayıt hedefi bulunamadı.');
        }

        return $this->poster->post(new PostAccountTransactionData(
            accountId: (int) $original->account_id,
            postingDate: $postingDate,
            signedAmount: $this->amounts->negate((string) $original->signed_amount),
            sourceEffect: $sourceEffect,
            memo: $memo,
            reversalOfTransactionId: $originalTransactionId,
        ));
    }
}

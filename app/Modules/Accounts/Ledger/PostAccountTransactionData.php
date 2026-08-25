<?php

namespace App\Modules\Accounts\Ledger;

use App\Foundation\Identity\SourceEffectIdentity;
use DateTimeImmutable;
use InvalidArgumentException;

final readonly class PostAccountTransactionData
{
    public function __construct(
        public int $accountId,
        public string $postingDate,
        public string $signedAmount,
        public SourceEffectIdentity $sourceEffect,
        public ?string $memo = null,
        public ?int $reversalOfTransactionId = null,
    ) {
        if ($accountId < 1) {
            throw new InvalidArgumentException('Cari hareketi persisted account id gerektirir.');
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $postingDate);
        if (! $date instanceof DateTimeImmutable || $date->format('Y-m-d') !== $postingDate) {
            throw new InvalidArgumentException('Cari hareket tarihi Y-m-d formatında geçerli bir tarih olmalıdır.');
        }

        if (! str_starts_with($sourceEffect->effectType, 'account.')) {
            throw new InvalidArgumentException('Cari hareket effect type account.* namespace içinde olmalıdır.');
        }

        if ($reversalOfTransactionId !== null && $reversalOfTransactionId < 1) {
            throw new InvalidArgumentException('Reversal transaction id persisted olmalıdır.');
        }
    }
}

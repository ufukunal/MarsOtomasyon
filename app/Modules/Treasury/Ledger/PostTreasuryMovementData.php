<?php

namespace App\Modules\Treasury\Ledger;

use App\Foundation\Identity\SourceEffectIdentity;

final readonly class PostTreasuryMovementData
{
    public function __construct(
        public SourceEffectIdentity $sourceEffect,
        public int $treasuryAccountId,
        public string $postingDate,
        public string $signedAmount,
        public string $movementType,
        public ?int $accountId = null,
        public ?int $paymentMethodId = null,
        public ?string $memo = null,
        public ?int $reversalOfMovementId = null,
    ) {}
}

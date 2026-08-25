<?php

namespace App\Modules\Accounts\Actions;

use App\Modules\Accounts\Enums\AccountType;
use App\Modules\Accounts\Enums\TaxIdentityType;

final readonly class CreateAccountData
{
    public function __construct(
        public string $code,
        public AccountType $type,
        public string $legalName,
        public ?string $tradeName,
        public TaxIdentityType $taxIdentityType,
        public ?string $taxNumber,
        public ?string $taxOffice,
        public string $bookCurrencyCode,
        public int $dueDays = 0,
        public string $discountRate = '0',
        public string $riskLimit = '0',
    ) {}
}

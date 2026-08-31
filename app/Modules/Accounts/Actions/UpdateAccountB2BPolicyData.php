<?php

namespace App\Modules\Accounts\Actions;

use App\Modules\B2B\Enums\B2BRiskBehavior;

final readonly class UpdateAccountB2BPolicyData
{
    public function __construct(
        public bool $isEnabled,
        public bool $allowOrders,
        public bool $showPrice,
        public bool $showStock,
        public bool $showBalance,
        public bool $showInvoices,
        public bool $showStatement,
        public bool $allowAddressManagement,
        public ?int $defaultWarehouseId,
        public B2BRiskBehavior $riskBehavior,
    ) {}
}

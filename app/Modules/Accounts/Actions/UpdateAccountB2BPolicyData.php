<?php

namespace App\Modules\Accounts\Actions;

final readonly class UpdateAccountB2BPolicyData
{
    public function __construct(
        public bool $isEnabled,
        public bool $allowOrders,
        public bool $showStock,
        public bool $showInvoices,
        public bool $showStatement,
        public bool $allowAddressManagement,
    ) {}
}

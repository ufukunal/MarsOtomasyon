<?php

namespace App\Modules\SalesInvoices\Actions;

use App\Modules\Accounts\Models\Account;
use App\Modules\Accounts\Models\AccountAddress;
use App\Modules\Dispatches\Models\Dispatch;
use App\Modules\SalesOrders\Models\SalesOrder;

final readonly class ResolvedSalesInvoiceSource
{
    /** @param list<ResolvedSalesInvoiceLine> $lines */
    public function __construct(
        public Account $account,
        public AccountAddress $billingAddress,
        public ?SalesOrder $salesOrder,
        public ?Dispatch $dispatch,
        public string $currencyCode,
        public array $lines,
    ) {}
}

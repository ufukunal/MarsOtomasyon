<?php

namespace App\Modules\SalesInvoices\Actions;

use App\Modules\SalesInvoices\Enums\SalesInvoiceMode;

final readonly class SalesInvoiceDraftData
{
    /** @param list<SalesInvoiceLineData> $lines */
    public function __construct(
        public SalesInvoiceMode $mode,
        public int $sourceBillingAddressId,
        public string $invoiceDate,
        public array $lines,
        public ?int $accountId = null,
        public ?int $salesOrderId = null,
        public ?int $dispatchId = null,
        public ?string $documentDiscountRate = null,
        public ?string $note = null,
    ) {}
}

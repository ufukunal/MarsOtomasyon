<?php

namespace App\Modules\SupplierInvoices\Actions;

final readonly class SupplierInvoiceDraftData
{
    /** @param list<SupplierInvoiceLineData> $lines */
    public function __construct(
        public int $purchaseOrderId,
        public string $invoiceDate,
        public ?string $note,
        public array $lines,
    ) {}
}

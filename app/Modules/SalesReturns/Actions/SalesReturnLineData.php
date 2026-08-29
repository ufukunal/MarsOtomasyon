<?php

namespace App\Modules\SalesReturns\Actions;

use InvalidArgumentException;

final readonly class SalesReturnLineData
{
    public function __construct(
        public int $salesInvoiceLineId,
        public string $quantity,
        public string $reasonCode,
    ) {
        if ($salesInvoiceLineId < 1) {
            throw new InvalidArgumentException('Satış iadesi persisted fatura satırı gerektirir.');
        }
        if (trim($reasonCode) === '' || strlen(trim($reasonCode)) > 64) {
            throw new InvalidArgumentException('Satış iadesi neden kodu zorunlu ve en fazla 64 karakter olmalıdır.');
        }
    }
}

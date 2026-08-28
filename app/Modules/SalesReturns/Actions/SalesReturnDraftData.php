<?php

namespace App\Modules\SalesReturns\Actions;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class SalesReturnDraftData
{
    /** @param list<SalesReturnLineData> $lines */
    public function __construct(
        public int $salesInvoiceId,
        public string $returnDate,
        public ?string $note,
        public array $lines,
    ) {
        if ($salesInvoiceId < 1) {
            throw new InvalidArgumentException('Satış iadesi persisted satış faturası gerektirir.');
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $returnDate);
        if (! $date instanceof DateTimeImmutable || $date->format('Y-m-d') !== $returnDate) {
            throw new InvalidArgumentException('İade tarihi Y-m-d formatında geçerli olmalıdır.');
        }
        if ($lines === [] || count($lines) > 200) {
            throw new InvalidArgumentException('Satış iadesi 1 ile 200 arasında satır içermelidir.');
        }
        if ($note !== null && mb_strlen(trim($note)) > 5000) {
            throw new InvalidArgumentException('Satış iadesi notu en fazla 5000 karakter olabilir.');
        }
    }
}

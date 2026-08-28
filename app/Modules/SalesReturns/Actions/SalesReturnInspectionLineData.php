<?php

namespace App\Modules\SalesReturns\Actions;

use InvalidArgumentException;

final readonly class SalesReturnInspectionLineData
{
    public function __construct(
        public int $salesReturnLineId,
        public string $acceptedQuantity,
        public string $rejectedQuantity,
        public string $restockQuantity,
        public ?string $conditionNotes = null,
    ) {
        if ($salesReturnLineId < 1) {
            throw new InvalidArgumentException('İade kontrolü persisted satır gerektirir.');
        }
        if ($conditionNotes !== null && mb_strlen(trim($conditionNotes)) > 1000) {
            throw new InvalidArgumentException('İade kontrol notu en fazla 1000 karakter olabilir.');
        }
    }
}

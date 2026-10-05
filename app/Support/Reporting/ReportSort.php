<?php

namespace App\Support\Reporting;

use DomainException;

final readonly class ReportSort
{
    public function __construct(
        public string $key,
        public string $direction = 'asc',
    ) {
        if (! in_array($this->direction, ['asc', 'desc'], true)) {
            throw new DomainException('Rapor sıralama yönü asc veya desc olmalıdır.');
        }
    }
}

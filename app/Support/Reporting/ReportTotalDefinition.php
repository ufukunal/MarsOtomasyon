<?php

namespace App\Support\Reporting;

final readonly class ReportTotalDefinition
{
    public function __construct(
        public string $key,
        public string $label,
        public string $type = 'decimal',
        public bool $costSensitive = false,
    ) {}
}

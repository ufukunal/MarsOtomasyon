<?php

namespace App\Support\Reporting;

final readonly class ReportColumnDefinition
{
    public function __construct(
        public string $key,
        public string $label,
        public string $type = 'string',
        public bool $sortable = true,
        public bool $costSensitive = false,
        public bool $defaultVisible = true,
    ) {}
}

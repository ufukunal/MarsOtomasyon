<?php

namespace App\Support\Reporting;

final readonly class ReportDrillDownDefinition
{
    public function __construct(
        public string $key,
        public string $label,
        public string $permission,
        public string $idColumn,
        public string $target,
    ) {}
}

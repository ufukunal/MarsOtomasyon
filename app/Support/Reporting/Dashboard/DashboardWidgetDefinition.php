<?php

namespace App\Support\Reporting\Dashboard;

final readonly class DashboardWidgetDefinition
{
    /**
     * @param  list<string>  $columns
     * @param  array<string,mixed>  $filters
     */
    public function __construct(
        public string $key,
        public string $title,
        public string $description,
        public string $reportKey,
        public array $columns,
        public array $filters = [],
        public ?string $totalKey = null,
        public string $valueType = 'integer',
    ) {}
}

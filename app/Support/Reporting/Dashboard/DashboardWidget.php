<?php

namespace App\Support\Reporting\Dashboard;

final readonly class DashboardWidget
{
    public function __construct(
        public string $key,
        public string $title,
        public string $description,
        public string $value,
        public ?string $url,
    ) {}
}

<?php

namespace App\Livewire\Components\DataTable;

final readonly class DateRangeFilter
{
    private function __construct(
        public string $key,
        public string $label,
    ) {
    }

    public static function make(string $key, string $label): self
    {
        return new self($key, $label);
    }
}

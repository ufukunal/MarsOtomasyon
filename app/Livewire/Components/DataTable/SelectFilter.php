<?php

namespace App\Livewire\Components\DataTable;

final readonly class SelectFilter
{
    /** @param array<int|string, string> $options */
    private function __construct(
        public string $key,
        public string $label,
        public array $options,
    ) {}

    public static function make(string $key, string $label): self
    {
        return new self($key, $label, []);
    }

    /** @param array<int|string, string> $options */
    public function options(array $options): self
    {
        return new self($this->key, $this->label, $options);
    }
}

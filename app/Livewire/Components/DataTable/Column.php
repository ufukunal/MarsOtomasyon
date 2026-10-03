<?php

namespace App\Livewire\Components\DataTable;

final class Column
{
    public bool $searchable = false;
    public bool $sortable = false;
    public bool $money = false;
    public bool $quantity = false;
    public string $align = 'start';

    private function __construct(
        public readonly string $key,
        public readonly string $label,
    ) {
    }

    public static function make(string $key, string $label): self
    {
        return new self($key, $label);
    }

    public function searchable(): self
    {
        $this->searchable = true;

        return $this;
    }

    public function sortable(): self
    {
        $this->sortable = true;

        return $this;
    }

    public function money(): self
    {
        $this->money = true;
        $this->align = 'end';

        return $this;
    }

    public function quantity(): self
    {
        $this->quantity = true;
        $this->align = 'end';

        return $this;
    }

    public function alignEnd(): self
    {
        $this->align = 'end';

        return $this;
    }
}

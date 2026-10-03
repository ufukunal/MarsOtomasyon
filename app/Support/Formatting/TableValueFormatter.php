<?php

namespace App\Support\Formatting;

use App\Livewire\Components\DataTable\Column;
use BackedEnum;
use DateTimeInterface;
use Stringable;

final class TableValueFormatter
{
    public static function format(Column $column, mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if ($value instanceof BackedEnum) {
            $value = $value->value;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('d.m.Y');
        }

        if (is_bool($value)) {
            return $value ? 'Evet' : 'Hayır';
        }

        if ($column->money) {
            return TrFormatter::money((string) $value);
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        if ($value instanceof Stringable) {
            return (string) $value;
        }

        return '';
    }
}

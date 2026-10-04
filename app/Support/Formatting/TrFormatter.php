<?php

namespace App\Support\Formatting;

use Carbon\CarbonInterface;

final class TrFormatter
{
    public static function money(string|int $value, int $displayScale = 2): string
    {
        $normalized = bcadd((string) $value, '0', 4);
        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '');

        $whole = preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $whole) ?? $whole;

        return $whole.($displayScale > 0 ? ','.substr(str_pad($fraction, $displayScale, '0'), 0, $displayScale) : '');
    }

    public static function quantity(string|int $value): string
    {
        $normalized = bcadd((string) $value, '0', 3);
        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '');

        $whole = preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $whole) ?? $whole;

        return $whole.','.substr(str_pad($fraction, 3, '0'), 0, 3);
    }

    public static function date(?CarbonInterface $date): string
    {
        return $date?->format('d.m.Y') ?? '';
    }
}

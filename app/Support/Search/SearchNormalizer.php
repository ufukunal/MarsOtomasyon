<?php

namespace App\Support\Search;

final class SearchNormalizer
{
    private const MAP = [
        'ı' => 'i', 'İ' => 'i', 'I' => 'i', 'i' => 'i',
        'ş' => 's', 'Ş' => 's', 'ğ' => 'g', 'Ğ' => 'g',
        'ü' => 'u', 'Ü' => 'u', 'ö' => 'o', 'Ö' => 'o',
        'ç' => 'c', 'Ç' => 'c', 'â' => 'a', 'Â' => 'a',
        'î' => 'i', 'Î' => 'i', 'û' => 'u', 'Û' => 'u',
    ];

    public static function make(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        $value = strtr($value, self::MAP);
        $value = mb_strtolower($value, 'UTF-8');
        $value = preg_replace('/[^a-z0-9]+/u', ' ', $value) ?? '';
        $value = preg_replace('/\s+/', ' ', $value) ?? '';

        return trim($value);
    }
}

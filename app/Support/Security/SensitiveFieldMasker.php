<?php

namespace App\Support\Security;

final class SensitiveFieldMasker
{
    public static function nationalId(?string $value, bool $canViewFull = false): ?string
    {
        if ($value === null || $value === '' || $canViewFull) {
            return $value;
        }

        if (strlen($value) < 5) {
            return '*****';
        }

        return substr($value, 0, 3).'*****'.substr($value, -2);
    }

    public static function phone(?string $value, bool $canViewFull = false): ?string
    {
        if ($value === null || $value === '' || $canViewFull) {
            return $value;
        }

        $digits = preg_replace('/\D+/', '', $value) ?? '';

        return strlen($digits) >= 4
            ? '***'.substr($digits, -4)
            : '****';
    }
}

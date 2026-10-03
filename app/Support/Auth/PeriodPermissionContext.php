<?php

namespace App\Support\Auth;

final class PeriodPermissionContext
{
    private const MASTER_PREFIXES = [
        'companies.',
        'users.',
        'roles.',
        'periods.',
        'company_copy_permissions.',
        'audit.',
        'print_profiles.',
    ];

    private static array $allow = [];
    private static array $deny = [];

    public static function use(array $overrides): void
    {
        self::$allow = self::normalize($overrides['allow'] ?? []);
        self::$deny = self::normalize($overrides['deny'] ?? []);
    }

    public static function clear(): void
    {
        self::$allow = [];
        self::$deny = [];
    }

    public static function decision(string $ability): ?bool
    {
        if (! self::isPeriodBusinessPermission($ability)) {
            return null;
        }

        if (in_array($ability, self::$deny, true)) {
            return false;
        }

        if (in_array($ability, self::$allow, true)) {
            return true;
        }

        return null;
    }

    private static function isPeriodBusinessPermission(string $ability): bool
    {
        foreach (self::MASTER_PREFIXES as $prefix) {
            if (str_starts_with($ability, $prefix)) {
                return false;
            }
        }

        return str_contains($ability, '.');
    }

    private static function normalize(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        return array_values(array_unique(array_filter(
            array_map(fn ($value): string => trim((string) $value), $values),
            fn (string $value): bool => $value !== '' && self::isPeriodBusinessPermission($value),
        )));
    }
}

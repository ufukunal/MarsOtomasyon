<?php

namespace App\Support\Auth;

/**
 * Company-specific business permission exceptions after membership validation.
 * This context does not replace role checks for Master/system abilities.
 */
final class CompanyPermissionContext
{
    private const MASTER_PREFIXES = [
        'companies.',
        'users.',
        'roles.',
        'audit.',
        'print_profiles.',
    ];

    /** @var list<string> */
    private static array $allow = [];

    /** @var list<string> */
    private static array $deny = [];

    /** @param array<string,mixed> $overrides */
    public static function use(array $overrides): void
    {
        self::$allow = self::normalize($overrides['allow'] ?? []);
        self::$deny = self::normalize($overrides['deny'] ?? []);
    }

    public static function decision(string $ability): ?bool
    {
        if (! self::isBusinessPermission($ability)) {
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

    public static function clear(): void
    {
        self::$allow = [];
        self::$deny = [];
    }

    private static function isBusinessPermission(string $ability): bool
    {
        foreach (self::MASTER_PREFIXES as $prefix) {
            if (str_starts_with($ability, $prefix)) {
                return false;
            }
        }

        return str_contains($ability, '.');
    }

    /** @return list<string> */
    private static function normalize(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        return array_values(array_unique(array_filter(
            array_map(fn ($value): string => trim((string) $value), $values),
            fn (string $value): bool => $value !== '' && self::isBusinessPermission($value),
        )));
    }
}

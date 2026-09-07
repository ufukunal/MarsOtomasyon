<?php

namespace App\Modules\UpdateCenter;

use InvalidArgumentException;

final class SemanticVersion
{
    public static function assertValid(string $version, string $field): void
    {
        self::parse($version, $field);
    }

    public static function compare(string $left, string $right): int
    {
        $leftParts = self::parse($left, 'version');
        $rightParts = self::parse($right, 'version');

        foreach ([0, 1, 2] as $index) {
            $comparison = self::compareNumericIdentifier($leftParts['core'][$index], $rightParts['core'][$index]);
            if ($comparison !== 0) {
                return $comparison;
            }
        }

        $leftPreRelease = $leftParts['pre_release'];
        $rightPreRelease = $rightParts['pre_release'];

        if ($leftPreRelease === null && $rightPreRelease === null) {
            return 0;
        }

        if ($leftPreRelease === null) {
            return 1;
        }

        if ($rightPreRelease === null) {
            return -1;
        }

        $limit = max(count($leftPreRelease), count($rightPreRelease));
        for ($index = 0; $index < $limit; $index++) {
            if (! array_key_exists($index, $leftPreRelease)) {
                return -1;
            }

            if (! array_key_exists($index, $rightPreRelease)) {
                return 1;
            }

            $comparison = self::comparePreReleaseIdentifier($leftPreRelease[$index], $rightPreRelease[$index]);
            if ($comparison !== 0) {
                return $comparison;
            }
        }

        return 0;
    }

    /** @return array{core: list<string>, pre_release: list<string>|null} */
    private static function parse(string $version, string $field): array
    {
        if ($version === '' || trim($version) !== $version) {
            throw new InvalidArgumentException("Update Center {$field} is not valid semantic versioning.");
        }

        $buildSplit = explode('+', $version, 3);
        if (count($buildSplit) > 2) {
            throw new InvalidArgumentException("Update Center {$field} is not valid semantic versioning.");
        }

        $precedence = $buildSplit[0];
        if (isset($buildSplit[1])) {
            self::assertDotIdentifiers($buildSplit[1], false, $field);
        }

        $preReleaseSplit = explode('-', $precedence, 2);
        $core = explode('.', $preReleaseSplit[0]);
        if (count($core) !== 3) {
            throw new InvalidArgumentException("Update Center {$field} is not valid semantic versioning.");
        }

        foreach ($core as $identifier) {
            if (! self::isValidNumericIdentifier($identifier)) {
                throw new InvalidArgumentException("Update Center {$field} is not valid semantic versioning.");
            }
        }

        $preRelease = null;
        if (isset($preReleaseSplit[1])) {
            $preRelease = self::assertDotIdentifiers($preReleaseSplit[1], true, $field);
        }

        return [
            'core' => $core,
            'pre_release' => $preRelease,
        ];
    }

    /** @return list<string> */
    private static function assertDotIdentifiers(string $value, bool $enforceNumericLeadingZeroRule, string $field): array
    {
        $identifiers = explode('.', $value);

        foreach ($identifiers as $identifier) {
            if ($identifier === '' || preg_match('/^[0-9A-Za-z-]+$/D', $identifier) !== 1) {
                throw new InvalidArgumentException("Update Center {$field} is not valid semantic versioning.");
            }

            if ($enforceNumericLeadingZeroRule && ctype_digit($identifier) && ! self::isValidNumericIdentifier($identifier)) {
                throw new InvalidArgumentException("Update Center {$field} is not valid semantic versioning.");
            }
        }

        return $identifiers;
    }

    private static function isValidNumericIdentifier(string $identifier): bool
    {
        return preg_match('/^(?:0|[1-9][0-9]*)$/D', $identifier) === 1;
    }

    private static function comparePreReleaseIdentifier(string $left, string $right): int
    {
        $leftNumeric = ctype_digit($left);
        $rightNumeric = ctype_digit($right);

        if ($leftNumeric && $rightNumeric) {
            return self::compareNumericIdentifier($left, $right);
        }

        if ($leftNumeric) {
            return -1;
        }

        if ($rightNumeric) {
            return 1;
        }

        return $left <=> $right;
    }

    private static function compareNumericIdentifier(string $left, string $right): int
    {
        $lengthComparison = strlen($left) <=> strlen($right);
        if ($lengthComparison !== 0) {
            return $lengthComparison;
        }

        return $left <=> $right;
    }
}

<?php

namespace Tests\ManualAudit\Support;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class AuditSource
{
    public static function root(): string
    {
        return dirname(__DIR__, 3);
    }

    public static function read(string $path): string
    {
        $fullPath = self::root().'/'.ltrim($path, '/');
        $contents = file_get_contents($fullPath);

        if ($contents === false) {
            throw new \RuntimeException("Audit source okunamadı: {$path}");
        }

        return $contents;
    }

    /**
     * @param list<string> $roots
     * @param list<string> $extensions
     * @return list<string>
     */
    public static function files(array $roots, array $extensions = ['php']): array
    {
        $files = [];

        foreach ($roots as $root) {
            $absolute = self::root().'/'.trim($root, '/');

            if (is_file($absolute)) {
                $files[] = self::relative($absolute);
                continue;
            }

            if (! is_dir($absolute)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($absolute, RecursiveDirectoryIterator::SKIP_DOTS),
            );

            /** @var SplFileInfo $file */
            foreach ($iterator as $file) {
                if (! $file->isFile()) {
                    continue;
                }

                $extension = strtolower($file->getExtension());

                if ($extensions !== [] && ! in_array($extension, $extensions, true)) {
                    continue;
                }

                $files[] = self::relative($file->getPathname());
            }
        }

        sort($files);

        return array_values(array_unique($files));
    }

    /**
     * @param list<string> $roots
     * @param list<string> $excludePrefixes
     * @return array<string, list<string>>
     */
    public static function grep(
        string $pattern,
        array $roots,
        array $excludePrefixes = [],
    ): array {
        $matches = [];

        foreach (self::files($roots, ['php', 'blade.php', 'env', 'xml', 'json', 'md']) as $path) {
            foreach ($excludePrefixes as $prefix) {
                if (str_starts_with($path, trim($prefix, '/'))) {
                    continue 2;
                }
            }

            $contents = self::read($path);

            if (! preg_match_all($pattern, $contents, $found, PREG_OFFSET_CAPTURE)) {
                continue;
            }

            foreach ($found[0] as [$text, $offset]) {
                $line = substr_count(substr($contents, 0, (int) $offset), "\n") + 1;
                $matches[$path][] = "L{$line}: ".trim((string) $text);
            }
        }

        return $matches;
    }

    public static function relative(string $absolute): string
    {
        $root = str_replace('\\', '/', self::root()).'/';
        $absolute = str_replace('\\', '/', $absolute);

        return str_starts_with($absolute, $root)
            ? substr($absolute, strlen($root))
            : $absolute;
    }

    /**
     * @return list<string>
     */
    public static function periodMigrationFiles(): array
    {
        return self::files(['database/migrations/period']);
    }

    /**
     * @return list<string>
     */
    public static function masterMigrationFiles(): array
    {
        return self::files(['database/migrations/master']);
    }

    /**
     * @return list<string>
     */
    public static function productionPhpFiles(): array
    {
        return self::files(['app', 'config', 'routes']);
    }

    /**
     * @param list<string> $paths
     * @param list<string> $required
     */
    public static function missingStrings(array $paths, array $required): array
    {
        $contents = '';

        foreach ($paths as $path) {
            $contents .= "\n".self::read($path);
        }

        return array_values(array_filter(
            $required,
            static fn (string $needle): bool => ! str_contains($contents, $needle),
        ));
    }

    /**
     * @param list<string> $paths
     * @param list<string> $forbidden
     * @return list<string>
     */
    public static function presentStrings(array $paths, array $forbidden): array
    {
        $contents = '';

        foreach ($paths as $path) {
            $contents .= "\n".self::read($path);
        }

        return array_values(array_filter(
            $forbidden,
            static fn (string $needle): bool => str_contains($contents, $needle),
        ));
    }
}

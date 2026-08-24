<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class ArchitectureBoundaryTest extends TestCase
{
    public function test_foundation_never_depends_on_business_modules(): void
    {
        foreach ($this->phpFiles(__DIR__.'/../../app/Foundation') as $file) {
            self::assertStringNotContainsString(
                'App\\Modules\\',
                file_get_contents($file),
                "Foundation must not depend on Modules: {$file}",
            );
        }
    }

    #[DataProvider('forbiddenApplicationPatterns')]
    public function test_application_code_does_not_use_forbidden_foundation_patterns(string $pattern): void
    {
        foreach ($this->phpFiles(__DIR__.'/../../app') as $file) {
            self::assertDoesNotMatchRegularExpression(
                $pattern,
                file_get_contents($file),
                "Forbidden pattern found in {$file}",
            );
        }
    }

    public static function forbiddenApplicationPatterns(): array
    {
        return [
            'env outside config' => ['/\\benv\\s*\\(/'],
            'dd helper' => ['/\\bdd\\s*\\(/'],
            'dump helper' => ['/\\bdump\\s*\\(/'],
            'var_dump' => ['/\\bvar_dump\\s*\\(/'],
        ];
    }

    /** @return list<string> */
    private function phpFiles(string $directory): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }
}

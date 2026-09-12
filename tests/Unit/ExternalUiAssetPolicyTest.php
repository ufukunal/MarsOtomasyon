<?php

it('does not load external browser CSS JS fonts or image assets', function (): void {
    $roots = [
        resource_path('views'),
        resource_path('css'),
        resource_path('js'),
    ];

    $patterns = [
        '/<(?:script|link|img|source|iframe)\b[^>]*(?:src|href|srcset)\s*=\s*["\']\s*https?:\/\//i',
        '/@import\s+(?:url\()?\s*["\']?\s*https?:\/\//i',
        '/url\(\s*["\']?\s*https?:\/\//i',
    ];

    $violations = [];
    foreach ($roots as $root) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $contents = file_get_contents($file->getPathname());
            if (! is_string($contents)) {
                continue;
            }

            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $contents) === 1) {
                    $violations[] = str_replace(base_path().'/', '', $file->getPathname());
                    break;
                }
            }
        }
    }

    expect($violations)->toBe([]);
});

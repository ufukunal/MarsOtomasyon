<?php

it('does not load external browser CSS JS fonts or image assets', function (): void {
    $roots = [
        resource_path('views'),
        resource_path('css'),
        resource_path('js'),
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

            $patterns = [
                '/<(?:script|link)\b[^>]*(?:src|href)\s*=\s*["\']https?:\/\//i',
                '/@import\s+(?:url\()?\s*["\']?https?:\/\//i',
                '/url\(\s*["\']?https?:\/\//i',
                '/fonts\.googleapis\.com|fonts\.gstatic\.com|cdn\.jsdelivr\.net|cdnjs\.cloudflare\.com|unpkg\.com/i',
            ];
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

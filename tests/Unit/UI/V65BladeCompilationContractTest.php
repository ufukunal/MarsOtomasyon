<?php

it('contains no invalid parenthesized PHP directives in Blade templates', function (): void {
    $errors = [];
    $views = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(resource_path('views'), FilesystemIterator::SKIP_DOTS)
    );

    foreach ($views as $view) {
        if (! $view->isFile() || ! str_ends_with($view->getFilename(), '.blade.php')) {
            continue;
        }

        $source = file_get_contents($view->getPathname());
        if ($source !== false && preg_match('/@php\s*\(/', $source) === 1) {
            $errors[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $view->getPathname());
        }
    }

    expect($errors)->toBe([]);
});

it('lints compiled Blade templates before switching the Fresh web image', function (): void {
    $script = file_get_contents(base_path('ops/uat-fresh-web-update.sh'));

    expect($script)
        ->toContain('php /var/www/html/artisan view:cache')
        ->toContain('php -l "$compiled"')
        ->toContain('IMAGE_BLADE_LINT_OK');
});

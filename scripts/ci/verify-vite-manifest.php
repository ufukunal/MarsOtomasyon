<?php

declare(strict_types=1);

/**
 * Verify every static @vite asset referenced by a Blade view exists in the
 * production manifest AND that the emitted file is present on disk.
 *
 * The previous v65 outage was caused by a CSS entry in the authenticated
 * layout that was missing from vite.config.js, invisible to view:cache
 * and unauthenticated HTTP smoke tests.
 */
$root = dirname(__DIR__, 2);
$manifestPath = $root.'/public/build/manifest.json';

if (! is_file($manifestPath)) {
    fwrite(STDERR, "FAIL: Vite manifest missing: {$manifestPath}\n");
    exit(1);
}

$manifest = json_decode(file_get_contents($manifestPath) ?: '', true);

if (! is_array($manifest)) {
    fwrite(STDERR, "FAIL: Vite manifest is invalid JSON.\n");
    exit(1);
}

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root.'/resources/views', FilesystemIterator::SKIP_DOTS),
);
$checked = [];
$errors = [];

foreach ($iterator as $view) {
    if (! $view->isFile() || ! str_ends_with($view->getFilename(), '.blade.php')) {
        continue;
    }

    $contents = file_get_contents($view->getPathname());

    if ($contents === false) {
        $errors[] = 'Cannot read '.$view->getPathname();

        continue;
    }

    // Every used @vite array must consist of static quoted asset paths.
    if (! preg_match_all('/@vite\s*\(\s*\[([^\]]*)\]\s*\)/s', $contents, $expressions)) {
        continue;
    }

    foreach ($expressions[1] as $expression) {
        preg_match_all('/[\\x27"]([^\\x27"]+)[\\x27"]/', $expression, $paths);
        foreach ($paths[1] as $input) {
            $checked[$input] = true;
            $output = $manifest[$input]['file'] ?? null;

            if (! is_string($output) || $output === '') {
                $errors[] = "{$view->getFilename()}: missing manifest entry for {$input}";

                continue;
            }

            if (str_contains($output, '..') || ! is_file($root.'/public/build/'.$output)) {
                $errors[] = "{$view->getFilename()}: emitted asset missing for {$input}: {$output}";
            }
        }
    }
}

if ($checked === []) {
    $errors[] = 'No static Blade @vite input arrays were found.';
}

if ($errors !== []) {
    foreach ($errors as $error) {
        fwrite(STDERR, "FAIL: {$error}\n");
    }

    exit(1);
}

echo 'PASS: '.count($checked).' Blade Vite entrypoints exist in manifest and build output: '
    .implode(', ', array_keys($checked)).PHP_EOL;

<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$reportDir = $root.'/storage/app/assurance';

if (! is_dir($reportDir) && ! mkdir($reportDir, 0775, true) && ! is_dir($reportDir)) {
    fwrite(STDERR, "Unable to create assurance report directory.\n");
    exit(2);
}

/** @return list<string> */
function phpFiles(string $root, array $relativeRoots): array
{
    $files = [];

    foreach ($relativeRoots as $relativeRoot) {
        $directory = $root.'/'.$relativeRoot;
        if (! is_dir($directory)) {
            continue;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }
    }

    sort($files);

    return $files;
}

function relativePath(string $root, string $path): string
{
    return str_replace('\\', '/', substr($path, strlen($root) + 1));
}

/** @return array{code:string,severity:string,path:string,message:string} */
function finding(string $code, string $severity, string $path, string $message): array
{
    return compact('code', 'severity', 'path', 'message');
}

$files = phpFiles($root, ['app', 'bootstrap', 'routes']);
$blockers = [];
$findings = [];
$metrics = [
    'php_files' => count($files),
    'lines' => 0,
    'large_files' => 0,
];

foreach ($files as $file) {
    $relative = relativePath($root, $file);
    $contents = file_get_contents($file);
    if ($contents === false) {
        $blockers[] = finding('unreadable-source', 'Critical', $relative, 'Source file could not be read.');
        continue;
    }

    $lines = substr_count($contents, "\n") + 1;
    $metrics['lines'] += $lines;

    if ($lines > 1200) {
        $metrics['large_files']++;
        $findings[] = finding('large-source-file', 'Medium', $relative, "Source file has {$lines} lines; review decomposition opportunities.");
    }

    if (str_starts_with($relative, 'app/Foundation/') && preg_match('/\\bApp\\\\Modules\\\\/', $contents) === 1) {
        $blockers[] = finding('foundation-module-dependency', 'Critical', $relative, 'Foundation must remain independent from App\\Modules.');
    }

    if (preg_match('/\\benv\\s*\\(/', $contents) === 1) {
        $blockers[] = finding('runtime-env-access', 'High', $relative, 'Runtime code must read configuration through config(), not env().');
    }

    if (str_starts_with($relative, 'app/') && preg_match('/\\b(?:dd|dump|ray|eval)\\s*\\(/', $contents) === 1) {
        $blockers[] = finding('debug-or-dynamic-eval', 'Critical', $relative, 'Debug or dynamic evaluation helper found in application code.');
    }
}

$blockers = array_values(array_unique($blockers, SORT_REGULAR));
$findings = array_values(array_unique($findings, SORT_REGULAR));

$sha = trim((string) shell_exec('git rev-parse HEAD 2>/dev/null'));
$report = [
    'schema_version' => 1,
    'slice' => 'M34-B',
    'generated_at' => gmdate(DATE_ATOM),
    'git_sha' => $sha !== '' ? $sha : null,
    'summary' => [
        'blockers' => count($blockers),
        'findings' => count($findings),
        'metrics' => $metrics,
    ],
    'blockers' => $blockers,
    'findings' => $findings,
];

$encoded = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n";
file_put_contents($reportDir.'/architecture-report.json', $encoded);

fwrite(STDOUT, sprintf(
    "Architecture assurance: %d files, %d blockers, %d findings.\n",
    $metrics['php_files'],
    count($blockers),
    count($findings),
));

if ($blockers !== []) {
    foreach ($blockers as $blocker) {
        fwrite(STDERR, sprintf("[%s] %s: %s\n", $blocker['severity'], $blocker['path'], $blocker['message']));
    }
    exit(1);
}

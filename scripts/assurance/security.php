<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$reportDir = $root.'/storage/app/assurance';

if (! is_dir($reportDir) && ! mkdir($reportDir, 0775, true) && ! is_dir($reportDir)) {
    fwrite(STDERR, "Unable to create assurance report directory.\n");
    exit(2);
}

/** @return list<string> */
function securityPhpFiles(string $root): array
{
    $files = [];

    foreach (['app', 'bootstrap', 'routes'] as $relativeRoot) {
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

/** @return array{code:string,severity:string,path:string,message:string} */
function securityFinding(string $code, string $severity, string $path, string $message): array
{
    return compact('code', 'severity', 'path', 'message');
}

function securityRelativePath(string $root, string $path): string
{
    return str_replace('\\', '/', substr($path, strlen($root) + 1));
}

/** @param array<string, mixed> $dependencies */
function inspectDependencyConstraints(array $dependencies, string $manifest, array &$blockers): void
{
    foreach ($dependencies as $package => $constraint) {
        if (! is_string($constraint)) {
            continue;
        }

        $normalized = strtolower(trim($constraint));
        $unsafe = $normalized === '*'
            || $normalized === 'latest'
            || str_contains($normalized, 'dev-master')
            || str_contains($normalized, 'dev-main')
            || str_contains($normalized, '@dev')
            || str_starts_with($normalized, 'git+')
            || str_starts_with($normalized, 'http://')
            || str_starts_with($normalized, 'https://');

        if ($unsafe) {
            $blockers[] = securityFinding(
                'unsafe-dependency-constraint',
                'High',
                $manifest,
                "Dependency {$package} uses unsafe constraint {$constraint}.",
            );
        }
    }
}

$blockers = [];
$findings = [];
$files = securityPhpFiles($root);

foreach ($files as $file) {
    $relative = securityRelativePath($root, $file);
    $contents = file_get_contents($file);
    if ($contents === false) {
        $blockers[] = securityFinding('unreadable-source', 'Critical', $relative, 'Source file could not be read.');

        continue;
    }

    $blockerPatterns = [
        'dynamic-eval' => ['/\\beval\\s*\\(/', 'Dynamic eval() is prohibited in runtime code.'],
        'unsafe-unserialize' => ['/\\bunserialize\\s*\\(/', 'Direct unserialize() is prohibited in runtime code.'],
        'tls-verification-disabled' => [
            '/(?:CURLOPT_SSL_VERIFYPEER\\s*=>\\s*false|[\'\"]verify[\'\"]\\s*=>\\s*false)/',
            'TLS peer verification must not be disabled.',
        ],
    ];

    foreach ($blockerPatterns as $code => [$pattern, $message]) {
        if (preg_match($pattern, $contents) === 1) {
            $blockers[] = securityFinding($code, 'Critical', $relative, $message);
        }
    }

    $findingPatterns = [
        'process-execution' => ['/\\b(?:exec|system|passthru|proc_open|popen|shell_exec)\\s*\\(/', 'Process execution primitive requires security review.'],
        'raw-sql' => ['/\\b(?:DB::raw|whereRaw|havingRaw|orderByRaw|selectRaw)\\s*\\(/', 'Raw SQL primitive requires input-boundary review.'],
    ];

    foreach ($findingPatterns as $code => [$pattern, $message]) {
        if (preg_match($pattern, $contents) === 1) {
            $findings[] = securityFinding($code, 'Medium', $relative, $message);
        }
    }
}

foreach (['composer.lock', 'package-lock.json'] as $lockFile) {
    if (! is_file($root.'/'.$lockFile)) {
        $blockers[] = securityFinding('missing-lockfile', 'High', $lockFile, 'Dependency lock file is required.');
    }
}

$composerPath = $root.'/composer.json';
$composer = json_decode((string) file_get_contents($composerPath), true, 512, JSON_THROW_ON_ERROR);
if (is_array($composer)) {
    foreach (['require', 'require-dev'] as $section) {
        $dependencies = $composer[$section] ?? [];
        if (is_array($dependencies)) {
            inspectDependencyConstraints($dependencies, 'composer.json', $blockers);
        }
    }
}

$packagePath = $root.'/package.json';
if (is_file($packagePath)) {
    $package = json_decode((string) file_get_contents($packagePath), true, 512, JSON_THROW_ON_ERROR);
    if (is_array($package)) {
        foreach (['dependencies', 'devDependencies'] as $section) {
            $dependencies = $package[$section] ?? [];
            if (is_array($dependencies)) {
                inspectDependencyConstraints($dependencies, 'package.json', $blockers);
            }
        }
    }
}

$blockers = array_values(array_unique($blockers, SORT_REGULAR));
$findings = array_values(array_unique($findings, SORT_REGULAR));
$sha = trim((string) shell_exec('git rev-parse HEAD 2>/dev/null'));
$report = [
    'schema_version' => 1,
    'slice' => 'M34-C',
    'generated_at' => gmdate(DATE_ATOM),
    'git_sha' => $sha !== '' ? $sha : null,
    'summary' => [
        'scanned_php_files' => count($files),
        'blockers' => count($blockers),
        'findings' => count($findings),
    ],
    'blockers' => $blockers,
    'findings' => $findings,
];

file_put_contents(
    $reportDir.'/security-report.json',
    json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n",
);

fwrite(STDOUT, sprintf(
    "Security assurance: %d PHP files, %d blockers, %d review findings.\n",
    count($files),
    count($blockers),
    count($findings),
));

if ($blockers !== []) {
    foreach ($blockers as $blocker) {
        fwrite(STDERR, sprintf("[%s] %s: %s\n", $blocker['severity'], $blocker['path'], $blocker['message']));
    }

    exit(1);
}

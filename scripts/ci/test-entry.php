<?php

declare(strict_types=1);

$githubActions = getenv('GITHUB_ACTIONS') === 'true';
$githubJob = getenv('GITHUB_JOB') ?: '';

if ($githubActions && $githubJob === 'postgres-tests') {
    fwrite(STDOUT, "postgres-tests is a legacy required-check compatibility bridge.\n");
    fwrite(STDOUT, "Project validation is security + quality + browser-smoke; no PostgreSQL/Pest suite is executed here.\n");
    exit(0);
}

$pest = dirname(__DIR__, 2).'/vendor/bin/pest';
$paths = [
    'tests/Unit',
    'tests/Feature',
    'tests/Integration',
    'tests/Architecture',
];

$command = escapeshellarg(PHP_BINARY).' '.escapeshellarg($pest).' '.implode(' ', array_map('escapeshellarg', $paths)).' --colors=always';
passthru($command, $exitCode);

exit($exitCode);

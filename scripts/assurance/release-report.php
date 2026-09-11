<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$evidenceRoot = $root.'/evidence';
$reportDir = $root.'/storage/app/assurance';

if (! is_dir($reportDir) && ! mkdir($reportDir, 0775, true) && ! is_dir($reportDir)) {
    fwrite(STDERR, "Unable to create assurance report directory.\n");

    exit(2);
}

$gateResults = [
    'security' => getenv('SECURITY_RESULT') ?: 'unknown',
    'quality' => getenv('QUALITY_RESULT') ?: 'unknown',
    'browser-smoke' => getenv('BROWSER_SMOKE_RESULT') ?: 'unknown',
];

$expectedEvidence = [
    'inventory.json',
    'coverage-map.json',
    'route-authorization-map.json',
    'architecture-report.json',
    'security-report.json',
    'auth-tenant-report.json',
    'accounting-invariants-report.json',
    'database-lifecycle-report.json',
    'recovery-failure-report.json',
    'browser-report.json',
];

$foundEvidence = [];
$evidenceIssues = [];
$coverageDebt = [];

if (is_dir($evidenceRoot)) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($evidenceRoot, RecursiveDirectoryIterator::SKIP_DOTS),
    );

    foreach ($iterator as $file) {
        if (! $file instanceof SplFileInfo || ! $file->isFile()) {
            continue;
        }

        $basename = $file->getBasename();
        if (! in_array($basename, $expectedEvidence, true)) {
            continue;
        }

        $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
        $foundEvidence[$basename] = $relative;

        $contents = file_get_contents($file->getPathname());
        if ($contents === false) {
            $evidenceIssues[] = $basename.': unreadable evidence file';

            continue;
        }

        try {
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $evidenceIssues[] = $basename.': invalid JSON evidence';

            continue;
        }

        if (! is_array($decoded)) {
            $evidenceIssues[] = $basename.': evidence root must be an object';

            continue;
        }

        $status = $decoded['status'] ?? null;
        if (is_string($status) && ! in_array(strtolower($status), ['passed', 'success'], true)) {
            $evidenceIssues[] = $basename.': reported status '.$status;
        }

        $blockers = $decoded['blockers'] ?? null;
        if (is_array($blockers) && $blockers !== []) {
            $evidenceIssues[] = $basename.': contains '.count($blockers).' blocker(s)';
        }

        $summaryBlockers = $decoded['summary']['blockers'] ?? null;
        if (is_int($summaryBlockers) && $summaryBlockers > 0) {
            $evidenceIssues[] = $basename.': summary reports '.$summaryBlockers.' blocker(s)';
        }

        if ($basename === 'coverage-map.json') {
            $criticalGaps = $decoded['critical_gaps'] ?? [];
            if (is_array($criticalGaps) && $criticalGaps !== []) {
                $coverageDebt = $criticalGaps;
            }
        }
    }
}

sort($expectedEvidence);
ksort($foundEvidence);
$missingEvidence = array_values(array_diff($expectedEvidence, array_keys($foundEvidence)));
$failedGates = array_keys(array_filter(
    $gateResults,
    static fn (string $result): bool => $result !== 'success',
));

$status = $failedGates === [] && $missingEvidence === [] && $evidenceIssues === [] ? 'passed' : 'failed';
$sha = trim((string) (getenv('FULL_ASSURANCE_CHECKOUT_REF') ?: ''));

$report = [
    'schema_version' => 1,
    'slice' => 'M34-H',
    'generated_at' => gmdate(DATE_ATOM),
    'git_sha' => $sha !== '' ? $sha : null,
    'status' => $status,
    'gate_results' => $gateResults,
    'failed_gates' => $failedGates,
    'expected_evidence' => $expectedEvidence,
    'found_evidence' => $foundEvidence,
    'missing_evidence' => $missingEvidence,
    'evidence_issues' => $evidenceIssues,
    'reported_coverage_debt' => $coverageDebt,
];

file_put_contents(
    $reportDir.'/release-report.json',
    json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n",
);

$summary = [
    '# M34 Full Assurance Release Report',
    '',
    '- SHA: `'.($sha !== '' ? $sha : 'unknown').'`',
    '- Status: **'.strtoupper($status).'**',
    '- security: `'.$gateResults['security'].'`',
    '- quality: `'.$gateResults['quality'].'`',
    '- browser-smoke: `'.$gateResults['browser-smoke'].'`',
    '- Evidence files: '.count($foundEvidence).'/'.count($expectedEvidence),
    '- Reported coverage debt items: '.count($coverageDebt),
];

if ($failedGates !== []) {
    $summary[] = '- Failed gates: '.implode(', ', $failedGates);
}

if ($missingEvidence !== []) {
    $summary[] = '- Missing evidence: '.implode(', ', $missingEvidence);
}

if ($evidenceIssues !== []) {
    $summary[] = '- Evidence issues: '.implode('; ', $evidenceIssues);
}

file_put_contents($reportDir.'/release-summary.md', implode("\n", $summary)."\n");

fwrite(STDOUT, sprintf(
    "M34-H release report: status=%s gates=%s evidence=%d/%d issues=%d coverage_debt=%d\n",
    $status,
    json_encode($gateResults, JSON_THROW_ON_ERROR),
    count($foundEvidence),
    count($expectedEvidence),
    count($evidenceIssues),
    count($coverageDebt),
));

exit($status === 'passed' ? 0 : 1);

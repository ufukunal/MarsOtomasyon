#!/usr/bin/env bash
set -euo pipefail

files=(
  tests/Feature/HealthReadinessTest.php
  tests/Feature/RecoveryModeCommandTest.php
  tests/Feature/RecoverySafetyTest.php
  tests/Feature/OffsiteBackupReadinessCheckTest.php
  tests/Feature/UpdateCenter/UpdateCenterStabilityTest.php
  tests/Integration/Operations/M23ProductionProviderKillSwitchTest.php
)

for file in "${files[@]}"; do
  if [[ ! -f "$file" ]]; then
    echo "::error::Required recovery/failure assurance test is missing: $file"
    exit 2
  fi
done

vendor/bin/pest "${files[@]}" --colors=always

mkdir -p storage/app/assurance
php -r '
$files = array_slice($argv, 1);
$report = [
    "schema_version" => 1,
    "slice" => "M34-G",
    "generated_at" => gmdate(DATE_ATOM),
    "git_sha" => trim((string) shell_exec("git rev-parse HEAD 2>/dev/null")),
    "status" => "passed",
    "scope" => ["readiness", "recovery-mode", "recovery-safety", "offsite-backup", "update-stability", "provider-kill-switch", "browser-regression"],
    "tests" => $files,
];
file_put_contents("storage/app/assurance/recovery-failure-report.json", json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL);
' -- "${files[@]}"

echo "M34-G recovery/failure assurance passed (${#files[@]} focused test files); browser regression follows in the same gate."

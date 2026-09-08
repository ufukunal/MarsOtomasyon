#!/usr/bin/env bash
set -euo pipefail

files=(
  tests/Integration/Core/AuthenticationTest.php
  tests/Integration/Core/AuthorizationTest.php
  tests/Integration/Core/ActiveCompanyContextTest.php
  tests/Integration/Core/BranchContextManagementTest.php
  tests/Integration/B2B/B2BAuthenticationTest.php
  tests/Integration/B2B/B2BPortalExitGateTest.php
  tests/Integration/Accounts/AccountB2BPolicyTest.php
  tests/Integration/Products/ProductCrudAuthorizationTest.php
  tests/Integration/Treasury/M10StatementAuthorityTest.php
)

for file in "${files[@]}"; do
  if [[ ! -f "$file" ]]; then
    echo "::error::Required auth/tenant assurance test is missing: $file"
    exit 2
  fi
done

vendor/bin/pest "${files[@]}" --colors=always

mkdir -p storage/app/assurance
php -r '
$files = array_slice($argv, 1);
$report = [
    "schema_version" => 1,
    "slice" => "M34-D",
    "generated_at" => gmdate(DATE_ATOM),
    "git_sha" => trim((string) shell_exec("git rev-parse HEAD 2>/dev/null")),
    "status" => "passed",
    "scope" => ["authentication", "authorization", "company-context", "branch-context", "b2b", "tenant-sensitive-authority"],
    "tests" => $files,
];
file_put_contents("storage/app/assurance/auth-tenant-report.json", json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL);
' -- "${files[@]}"

echo "M34-D auth/RBAC/tenant assurance passed (${#files[@]} focused test files)."

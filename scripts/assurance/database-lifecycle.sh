#!/usr/bin/env bash
set -euo pipefail

files=(
  tests/Integration/PostgreSqlFoundationTest.php
  tests/Feature/PostgresQueryPlanRegressionTest.php
  tests/Feature/IdempotencyTransactionBoundaryTest.php
  tests/Feature/OutboxLeaseManagerTest.php
  tests/Integration/Imports/LegacyMigrationControlTest.php
  tests/Integration/SalesOrders/SalesOrderReservationTwoPhaseTest.php
  tests/Integration/Inventory/StockReservationTest.php
)

for file in "${files[@]}"; do
  if [[ ! -f "$file" ]]; then
    echo "::error::Required database assurance test is missing: $file"
    exit 2
  fi
done

php artisan migrate:status --no-interaction
php artisan migrate --force --no-interaction
vendor/bin/pest "${files[@]}" --colors=always

mkdir -p storage/app/assurance
php -r '
$files = array_slice($argv, 1);
$report = [
    "schema_version" => 1,
    "slice" => "M34-F",
    "generated_at" => gmdate(DATE_ATOM),
    "git_sha" => trim((string) shell_exec("git rev-parse HEAD 2>/dev/null")),
    "status" => "passed",
    "scope" => ["postgres-foundation", "migration-idempotency", "query-plan", "transaction-boundary", "outbox-lease", "reservation-concurrency", "stock-concurrency"],
    "tests" => $files,
];
file_put_contents("storage/app/assurance/database-lifecycle-report.json", json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL);
' -- "${files[@]}"

echo "M34-F database lifecycle/integrity/concurrency assurance passed (${#files[@]} focused test files)."

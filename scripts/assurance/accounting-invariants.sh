#!/usr/bin/env bash
set -euo pipefail

files=(
  tests/Integration/SalesInvoices/SalesInvoiceAccountEffectTest.php
  tests/Integration/SalesInvoices/SalesInvoicePricingTest.php
  tests/Integration/SalesInvoices/SalesInvoiceStockEffectMatrixTest.php
  tests/Integration/Accounts/AccountBalanceStatementTest.php
  tests/Integration/Accounts/AccountTransactionLedgerTest.php
  tests/Integration/Core/TaxCurrencyPostingPeriodTest.php
  tests/Integration/Treasury/M10TreasuryTest.php
  tests/Integration/SupplierInvoices/SupplierInvoiceTest.php
)

for file in "${files[@]}"; do
  if [[ ! -f "$file" ]]; then
    echo "::error::Required accounting assurance test is missing: $file"
    exit 2
  fi
done

vendor/bin/pest "${files[@]}" --colors=always

mkdir -p storage/app/assurance
php -r '
$files = array_slice($argv, 1);
$report = [
    "schema_version" => 1,
    "slice" => "M34-E",
    "generated_at" => gmdate(DATE_ATOM),
    "git_sha" => trim((string) shell_exec("git rev-parse HEAD 2>/dev/null")),
    "status" => "passed",
    "scope" => ["invoice-account-effects", "pricing", "stock-effects", "account-balance", "ledger", "tax-currency-period", "treasury", "supplier-invoices"],
    "tests" => $files,
];
file_put_contents("storage/app/assurance/accounting-invariants-report.json", json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL);
' -- "${files[@]}"

echo "M34-E accounting/business invariant assurance passed (${#files[@]} focused test files)."

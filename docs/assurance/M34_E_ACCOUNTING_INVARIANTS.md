# M34 E — Accounting and business invariant assurance

Slice E adds a focused business-invariant regression suite to the existing `browser-smoke` self-hosted runner after isolated PostgreSQL setup.

The suite executes existing regression tests for sales-invoice account effects, pricing, stock effects, account balances, transaction ledger integrity, tax/currency/posting-period rules, treasury, and supplier invoices. Missing expected test files fail closed.

On success it writes `storage/app/assurance/accounting-invariants-report.json` with the exact tested file set and invariant categories.

No additional CI job is introduced; the real Foundation gates remain exactly `security`, `quality`, and `browser-smoke`.

# MarsProject V4 — Fresh test suite

Every V4 file was newly authored against the current production code in `main`. No V1/V2/V3 tests were restored or cherry-picked.

## Existing suites

- **Unit:** Money and VAT calculations, purchase and sale due dates, invoice discounts, stock balances and validation, production costing, price boundaries, bank statements, imports, rendering, masking, file security, company/period invariants, four ecommerce payload builders, channel rules, registry guards, backup preflight and reporting.
- **Contracts:** Production PHP symbol inventory (`tests/Contracts/source-manifest.json`), 181 action class method signatures, Livewire routes, authorization wrappers, migration and connection inventories. Contracts only establish shape and authorization requirements, not end-to-end correctness.
- **Feature:** Guest access denial for major module routes, unsigned channel asset links and route protection.
- **Integration:** Opt-in, disposable-local-PostgreSQL fixtures for real stock movement, reservations, sales/purchase return quantities, financial document constraints, numbering, period closure, optimistic version locks, user permissions, marketplace event idempotency, supplier price deviations, and per-module database schemas.
- **Performance:** An opt-in, bounded microbenchmark for pure fixed-point arithmetic only. It is **not** application load testing.

## Safety

**No Pest tests have been executed as part of authoring this package.** GitHub `quality.yml` automatically runs static Pint/Larastan checks and a frontend build on pushes; it does not execute Pest.

- All integration database targets are fixed to `127.0.0.1`, the isolated `mars_test` role and the separate `mars_test_master` / `mars_test_period` databases. They must exist independently, be disposable and contain the matching migrated schema.
- Integration tests write **only temporary fixture rows within explicit transactions**, then roll them back. Schema contract checks are read-only. No migrations, database creation/drop or production restoration are performed by these tests.
- The local PostgreSQL integration suite is skipped unless `MARS_INTEGRATION_TESTS_APPROVED=I_APPROVE_LOCAL_TEST_ONLY`.
- The optional microbenchmark is skipped unless `MARS_PERFORMANCE_TESTS_APPROVED=I_APPROVE_LOCAL_BENCHMARK`.
- All suite invocations through `scripts/ci/test-v4.sh` require `MARS_TEST_EXECUTION_APPROVED=MARS_V4_EXECUTION_APPROVED`. These flags are gates, not substitutes for separate explicit user execution authorization.
- Nothing in this package is permission to access PROD/Tailscale systems, production databases, shared Valkey namespaces, secrets or operational data.

## Incomplete coverage: deliberately explicit

The manifest indexes all production classes, but **does not prove all their behavior**. Executable tests cover selected business flows and failure paths, not every branch of all 181 actions. Outstanding work includes complete sales/purchasing/finance posting and reversals; cross-company data transfers and period carry; upload and printing end-to-end workflows; API/webhook error/retry/pagination/signature fixtures for all four channels; real Valkey queue contention/concurrent workers; browser-driven Livewire journeys; actual export files; full backup/restore disaster recovery and realistic performance/load tests. Those require new isolated fixtures, mock servers and/or browser/host infrastructure before execution.

Do not report 100% coverage or green test results: execution has not been authorized. Maintain `source-manifest.json` when application files change; `SourceInventoryTest` detects discrepancies.

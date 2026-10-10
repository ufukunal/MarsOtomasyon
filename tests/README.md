# MarsProject V4 — Fresh test package

Newly authored tests for the current `main` production tree. No historic V1/V2/V3 suite was restored, copied or cherry-picked.

## Suites and scope

- **Unit:** Fixed-point money, VAT/discount totals, stock and production cost math, finance/purchase due dates, posting profile matrix, return and import guards, CSV/JSON streaming, security and file validation, channel API payloads/HTTP fakes/N11 SOAP fixtures, reporting definitions, print/template safety, role/mutation boundaries, stateful Livewire form calculations and local archive checks.
- **Contracts:** Exact production symbol inventory (644 baseline symbols), action signature checks for 181 action classes, Livewire route classes, module route policies, migrations, PostgreSQL connections and operational commands. These are shape/policy checks, **not** a substitute for behavior tests.
- **Feature:** Unauthenticated HTTP route and Livewire screen denial.
- **Integration:** Separate manually provisioned **local-only** PostgreSQL databases and strictly rolled-back temporary fixture transactions: real cash/bank collection and payments, immutable posted documents, company/period authorization, period closure, transaction numbering, optimistic locks, stock movements and reservations, supplier price deviation, returns, marketplace webhook authentication/event idempotency, schema/ledger constraints, advisory-lock contention.
- **Optional Valkey integration:** Local 127.0.0.1 host, separate test key prefix, atomic duplicate claim and TTL. Extra opt-in gate.
- **Optional performance:** Bounded pure arithmetic microbenchmark. No shared load.
- **Optional browser:** `tests/Browser` independent Playwright package. Local, approval-gated real browser login/guest boundary and authenticated read-only smoke for ten modules with explicit fixture IDs.

## Important: execution and safety

Authoring V4 and passing GitHub's Pint/Larastan/frontend-build workflow **does not mean Pest, Playwright, Valkey or PostgreSQL integration tests passed**. These suites were not run during authoring, consistent with separate execution approval.

- `scripts/ci/test-v4.sh` requires `MARS_TEST_EXECUTION_APPROVED=MARS_V4_EXECUTION_APPROVED` for any mode.
- Integration tests additionally require `MARS_INTEGRATION_TESTS_APPROVED=I_APPROVE_LOCAL_TEST_ONLY` and previously prepared disposable PostgreSQL databases `mars_test_master` / `mars_test_period` on `127.0.0.1`, role `mars_test`. Fixtures are written inside transactions and rolled back. No automatic migrations, database drop, or backups.
- Valkey integration separately requires `MARS_VALKEY_TESTS_APPROVED=I_APPROVE_LOCAL_VALKEY_ONLY`, `127.0.0.1` host and `mars:test:` key prefix.
- Performance tests additionally require `MARS_PERFORMANCE_TESTS_APPROVED=I_APPROVE_LOCAL_BENCHMARK`.
- Browser tests additionally require `MARS_BROWSER_TESTS_APPROVED=I_APPROVE_LOCAL_BROWSER`; see `tests/Browser/README.md`. The browser target must be localhost on `127.0.0.1`.
- Never point these tests at PROD, CI's shared stateful databases, Tailscale PROD resources, real marketplace accounts or actual customer records. An execution flag is **not** a substitute for explicit user permission to run the tests.

## Remaining work that cannot honestly be marked completed

The 181 action classes have been inventoried but **not every success, failure and concurrency branch has a dedicated executable scenario**. Full document posting/reversal chains, period carry involving complete and realistic distinct company DBs, cross-tenant copying, complete marketplace pagination/retry flows, password reset, browser CRUD operations, real multi-worker Valkey concurrency, disaster-recovery restorations and realistic production-scale performance tests need additional isolated fixtures and/or infrastructure and separate execution approval.

Source inventory is `tests/Contracts/source-manifest.json`. Keep it synchronized with `app/`. Do not equate "file exists" with "test passed" or report a 100% coverage figure until executable branch/line coverage has been obtained.

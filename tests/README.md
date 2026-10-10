# MarsProject TEST V4 — Fresh test package

V4 covers Unit, Contracts, Feature, Integration, optional Valkey, optional Performance and separate Playwright browser suites. It is not evidence that those tests have been executed. The GitHub quality workflow runs only static checks and frontend compilation.

## Approved two-VM test topology (2026-10-10)

- **CI VM:** Four existing self-hosted runners; TEST V4 targets `mars-ubuntu-04` / `mars-r4`. No PostgreSQL or Valkey **server/container** installation on CI. Only PHP 8.4+, `pdo_pgsql` and `phpredis` **client extensions**, SSH client, Composer and required test tools.
- **PROD VM:** `192.168.239.132` / `mars-prod-1.taila20365.ts.net`, SSH on port `10000` as `ufuk`. The **existing persistent Docker PostgreSQL and Valkey servers** live on this VM; V4 does not install, restart or replace them.
- **Connectivity:** `scripts/ci/test-v4-remote.sh` verifies SSH host identity and creates runner-local SSH forwards: `127.0.0.1:5432` → PROD VM `127.0.0.1:55432` (PostgreSQL), `127.0.0.1:6379` → PROD VM `127.0.0.1:6379` (Valkey). Loopback endpoints on CI are **tunnel client sockets**, not local servers.
- **Dedicated resources on the existing PROD services:** PostgreSQL databases `mars_test_master` and `mars_test_period`, role `mars_test`; Valkey ACL user `mars_test` restricted to `mars:test:*` keys. The Valkey test suite uses logical cache DB 1, so **ACL key-pattern restrictions are essential** even though the test prefix differs from production.
- All TEST resources must be provisioned and schema-migrated **independently of V4 execution**. The V4 runner never creates/drops databases, applies migrations, seeds production data, flushes Valkey or changes PROD service configuration.

## Repository and runner prerequisites

1. `.github/workflows/test-v4-manual.yml` uses manual `workflow_dispatch` only. Its default suite is the **read-only** `preflight`; a separate `isolation_confirmed=YES` is required for any test suite. The static-quality workflow does not run V4 suites.
2. The existing runner must be able to SSH to `ufuk@192.168.239.132:10000` with a pretrusted host key. Use an existing authorized SSH identity or dedicated GitHub secrets `MARS_V4_SSH_PRIVATE_KEY` and `MARS_V4_SSH_KNOWN_HOSTS`. An optional password fallback uses secret `MARS_V4_SSH_PASSWORD` **only if sshpass client is already available**. Never store or echo credentials in source, logs or workflow parameters. Verify SSH host-key fingerprints independently before adding known-hosts data.
3. Supply dedicated credentials in GitHub Secrets: `MARS_V4_DB_PASSWORD` for PostgreSQL role `mars_test`, and `MARS_V4_VALKEY_PASSWORD` for Valkey ACL account `mars_test`. These are **test-specific**, never production admin/app passwords.
4. On the PROD PostgreSQL server, `mars_test` must be non-superuser without CREATEDB, CREATEROLE, REPLICATION or BYPASSRLS, and it must not have CONNECT privileges to application databases other than the two dedicated test DBs. Database `postgres` is excluded from that guard as the default maintenance DB; never put application data there.
5. The Valkey `mars_test` ACL must **independently** be configured and reviewed to allow only required namespaced key commands; deny `FLUSHDB`, `FLUSHALL`, `CONFIG`, `ACL` mutations, scripting and unrelated key access. The read-only preflight verifies ACL identity and authentication; it cannot certify all ACL permissions. The operator must confirm them before selecting YES.
6. The test Master and Period schema, migrations, and disposable browser account/fixtures are prepared separately. No CI automatic database creation/migration/restore is permitted.

## Execution gates and suites

- `scripts/ci/test-v4-remote.sh preflight` first checks the correct R4 runner and remote PROD VM hostname, establishes SSH loopback forwarding, then runs `scripts/ci/v4-remote-preflight.php`. It performs read-only DB metadata checks and Valkey identity checks; no tests or data writes.
- Once the operator has **independently** confirmed the test DB and Valkey ACL isolation, manually select `contracts`, `unit`, `feature`, `integration`, `valkey` or `performance` in the same workflow and select `isolation_confirmed=YES`. Tests run on CI and connect to the dedicated resources over the tunnel. Only one V4 job runs at a time.
- `scripts/ci/test-v4.sh` requires `MARS_TEST_EXECUTION_APPROVED=MARS_V4_EXECUTION_APPROVED` for any PHP suite. Integration additionally needs `MARS_INTEGRATION_TESTS_APPROVED=I_APPROVE_LOCAL_TEST_ONLY`; Valkey additionally needs `MARS_VALKEY_TESTS_APPROVED=I_APPROVE_LOCAL_VALKEY_ONLY`; Performance needs `MARS_PERFORMANCE_TESTS_APPROVED=I_APPROVE_LOCAL_BENCHMARK`. The legacy `LOCAL` flag names refer to *runner-local tunnel endpoints*, not runner-hosted database servers.
- PostgreSQL fixtures use dedicated Master/Period DBs and roll back temporary transactions. They are not allowed to connect to production application databases.
- Valkey integration checks duplicate-claim atomicity, prefix isolation, and TTL. It writes only short-lived namespaced test keys.
- Performance suite is limited to existing arithmetic microbenchmarks; no shared-service load testing is authorized.
- Browser uses a **runner-local Laravel app** with the same isolated, SSH-forwarded test DBs and Valkey services; see `tests/Browser/README.md`. Browser execution is intentionally **not** exposed in the manual V4 workflow until its test-only app/fixture isolation is explicitly verified. No production accounts or live integrations.

## Functional coverage and known gaps

- **Unit:** fixed-point finance, tax and discount math; stock, purchasing and production calculations; channels and API fakes; uploads, reports, security, role policies and Livewire form state.
- **Contracts:** source inventory, action signatures, module routes, database and migration counts, environment and operational guardrails.
- **Feature:** unauthenticated HTTP/Livewire access denial.
- **Integration:** real PostgreSQL Master/Period isolation, stock/finance/ledger transactions, locks, idempotency, authorization, workflows and constraints.
- **Valkey:** isolated key namespace, SETNX and TTL.
- **Browser:** separate optional guest/login/read-only/isolated CRUD journeys behind additional gates.

A checked-in test file does not imply an executed scenario. Not every success/error/concurrency branch across the 181 production action classes has a dedicated behavior test. Full end-to-end posting/reversal and carryover, marketplace pagination/retries, password recovery, multi-worker contention, destructive restore and realistic production-scale load still need dedicated safe fixtures and coverage review.

The production symbol inventory is `tests/Contracts/source-manifest.json`. Do not report 100% test coverage or a PASS result without actual run evidence.

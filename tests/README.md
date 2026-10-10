# MarsProject V4 — TEST on the existing PROD VM data servers

CI runs on separate Ubuntu VM: R4 = `mars-ubuntu-04`. **No PostgreSQL or Valkey server/container is installed or required on CI.**
The existing persistent PostgreSQL and Valkey Docker services remain on the PROD VM.
On 2026-10-10, read-only CI checks proved that `mars-prod-1.taila20365.ts.net` (Tailscale) accepts PostgreSQL
connections on :55432, and Valkey on :6379 returns AUTH_REQUIRED.
The LAN address `192.168.239.132` did not accept these ports from R4.
OS SSH :10000 was reachable via Tailscale but the runner's default key login was denied.
V4 application data connections **do not require SSH**; they use direct Tailscale clients.

## Fail-closed execution and resource isolation

- Two dedicated PostgreSQL TEST databases must already exist: `mars_test_master`, `mars_test_period`.
- The TEST role `mars_test` must be non-superuser and lack CREATEDB, CREATEROLE, REPLICATION, BYPASSRLS, and CONNECT to all non-test application databases.
- Valkey must have a separate `mars_test` ACL account with access only to `mars:test:*` keys; FLUSHDB, FLUSHALL, administration and unrelated key access must be prohibited. Namespace prefix alone is insufficient isolation on a shared production service.
- Credentials for these accounts are expected **only** as GitHub Secrets `MARS_V4_DB_PASSWORD` and `MARS_V4_VALKEY_PASSWORD`. Never reuse PROD application/admin credentials.
- `.github/workflows/test-v4-manual.yml` is manual only. Select `preflight` first: `scripts/ci/test-v4-direct.sh` checks runner/Tailscale/DNS and `scripts/ci/v4-remote-preflight.php` performs read-only DB and Valkey account/privilege checks.
- Any TEST suite requires independent `isolation_confirmed=YES`, explicit suite approval and successful preflight. No CI database creation, migration, restore, flush, production container modifications or test execution without approval.
- `scripts/ci/test-v4.sh` requires `MARS_TEST_EXECUTION_APPROVED=MARS_V4_EXECUTION_APPROVED`; Integration additionally requires `MARS_INTEGRATION_TESTS_APPROVED=I_APPROVE_LOCAL_TEST_ONLY`; Valkey additionally requires `MARS_VALKEY_TESTS_APPROVED=I_APPROVE_LOCAL_VALKEY_ONLY`; Performance requires `MARS_PERFORMANCE_TESTS_APPROVED=I_APPROVE_LOCAL_BENCHMARK`. The legacy LOCAL token does not imply any DB server runs on CI.
- `phpunit.xml` intentionally pins data service hosts to the actual PROD VM Tailscale DNS name. **Playwright's `127.0.0.1` refers only to the runner-local Laravel web app**, never a PostgreSQL/Valkey server.

## Scope

Unit: finance math, tax, inventory, costing, imports, channel fixture contracts, reporting, security, Livewire state.
Contracts: source symbol manifest, 181 action class inventory, routes, migrations, DB and service isolation.
Feature: unauthenticated HTTP/Livewire access denial.
Integration: real dedicated PostgreSQL Master/Period transactions, invariants, locks, idempotency, authorization and stock/finance flows.
Valkey: dedicated ACL, SETNX duplicate prevention and TTL test keys.
Performance: bounded arithmetic-only microbenchmark, not shared service load.
Browser: separately approved runner-local app against isolated remote TEST DBs, see `tests/Browser/README.md`.

**Network reachability and static code checks do NOT mean the authenticated PostgreSQL, Valkey, Pest, Playwright or performance tests passed.** They remain NOT RUN until test-only credentials/ACLs and schema are verified. Source inventory: `tests/Contracts/source-manifest.json`. A–Z branch behavior coverage is not yet proven.

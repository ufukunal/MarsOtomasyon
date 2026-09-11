# Mars Full System Assurance

M34 keeps executable validation in exactly three self-hosted Foundation jobs: `security`, `quality`, and `browser-smoke`.

## Validation policy

Foundation executes exactly three real validation jobs:

- `security`
- `quality`
- `browser-smoke`

The historical required status name `postgres-tests` remains only as a compatibility gate. It performs no checkout, database setup, migration, or Pest suite and succeeds only when the three real jobs succeed.

`Full Assurance` is report-only. It is triggered after the merged-main Foundation workflow completes and never creates an additional test lane.

## Exact-SHA trust model

Foundation rejects untrusted fork code before checkout on self-hosted runners and validates the exact same-repository PR head SHA. On merged `main`, each evidence artifact is named for the exact Foundation SHA.

Full Assurance receives that completed Foundation run, requires its conclusion to be `success`, checks out its exact `head_sha`, proves the checkout, downloads only that run's exact-SHA artifacts, generates inventory evidence, and creates the final release report.

A green run for one SHA is not evidence for another SHA.

## Assurance coverage

The three Foundation jobs carry all executable M34 assurance:

- `quality`: formatting, PHPStan, architecture enforcement, frontend production build, and architecture evidence.
- `security`: Composer/npm dependency audits, tracked-secret scanning, SAST/supply-chain policy, and security evidence.
- `browser-smoke`: isolated PostgreSQL lifecycle, application boot, auth/RBAC/tenant isolation, accounting/business invariants, database integrity/concurrency, recovery/failure regression, and Playwright browser coverage.

The browser job writes focused machine-readable reports while running against the same isolated PostgreSQL environment. No standalone PostgreSQL or Full Assurance test lane is introduced.

## Release evidence

The report-only finalizer requires these exact-SHA evidence files:

- `inventory.json`
- `coverage-map.json`
- `route-authorization-map.json`
- `architecture-report.json`
- `security-report.json`
- `auth-tenant-report.json`
- `accounting-invariants-report.json`
- `database-lifecycle-report.json`
- `recovery-failure-report.json`
- `browser-report.json`

Missing evidence fails closed. A report with a failing status, non-empty `blockers`, or a positive `summary.blockers` count also fails closed. Inventory coverage debt remains explicitly reported instead of being silently discarded.

The final artifact contains `release-report.json`, `release-summary.md`, and the complete evidence bundle and is retained for 90 days.

## Release policy

- Critical findings block release.
- High findings block release unless a narrow, documented, owned, expiring waiver exists.
- Medium/Low findings remain visible and assigned for remediation.
- Tenant leakage, authorization bypass, financial/inventory reconciliation failure, migration failure, duplicate financial/stock effects, restore failure, and updater trust-chain failure are release blockers when detected by the supported assurance gates.

Suppressions may never be blanket ignores. Any waiver must identify the exact rule/path or CVE, rationale, owner, expiry, and mitigation.

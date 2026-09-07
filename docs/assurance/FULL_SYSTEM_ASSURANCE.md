# Mars Full System Assurance

M34 adds a release-oriented assurance layer without turning `.github/workflows/foundation.yml` into a long-running gate. Foundation remains the fast four-job PR gate. Full Assurance owns deeper inventory, architecture, security, authorization, functional, database, concurrency, browser/DAST and recovery evidence.

## Trust model

Self-hosted runners must never execute fork pull-request code through `pull_request_target`. Full Assurance uses the same trust rule as Foundation:

1. same-repository PRs only,
2. checkout the exact PR head SHA,
3. print and verify `git rev-parse HEAD`,
4. use read-only repository permissions,
5. keep dependency caches and mutable service namespaces isolated by run/lane when those lanes are added.

A green workflow is not evidence for a different commit. Release evidence always records the exact checked-out SHA.

## Slice A: inventory and coverage map

`App\Foundation\Assurance\AssuranceInventory` builds the inventory from the running Laravel application plus the repository filesystem. It does not rely on a manually maintained route count.

Surfaces currently inventoried:

- HTTP routes: methods, URI, action, middleware, mutation status, financial effect, tenant boundary, authentication, permission/trust boundary and risk.
- CLI: all registered Artisan commands, destructive/operational classification and test evidence.
- Async: queued jobs/listeners and visible retry policy.
- Data: migration-created tables and static counts of FK/unique/check/index declarations.
- External boundaries: HTTP, storage, email, webhook/provider integration usage.
- Operational primitives: backup, restore/recovery, production-safety and Update Center/deployment primitives.

Test evidence is correlated from `tests/Unit`, `tests/Feature`, `tests/Integration` and `tests/Browser`. Concurrency and recovery evidence are separately identified when the matching test exercises those classes of behavior.

### Artifacts

`php scripts/assurance/inventory.php` writes:

- `storage/app/assurance/inventory.json`
- `storage/app/assurance/coverage-map.json`
- `storage/app/assurance/route-authorization-map.json`

Each coverage row contains at least:

- `component`
- `type`
- `path/class`
- `mutates_state`
- `financial_effect`
- `tenant_scoped`
- `requires_auth`
- `required_permission`
- `unit_test`
- `feature_test`
- `integration_test`
- `browser_test`
- `concurrency_test`
- `recovery_test`
- `risk_level`
- `coverage_status`

Critical uncovered surfaces and unclassified mutating trust boundaries make the inventory command fail. Scanner failure is therefore fail-closed; the workflow still uploads any generated evidence with `if: always()`.

### Explicit trust classes

Not every legitimate mutation uses a normal session. The inventory keeps these boundaries distinct rather than falsely marking them as session-authenticated:

- web session: `auth`
- B2B session: `b2b.auth`
- integration token: `api.token`
- scanner agent: `scanner.auth`
- platform administration: `RequirePlatformAdmin`
- login/password reset entrypoints
- external channel webhook
- scanner enrollment

Adding a new public mutation without one of these classified boundaries is a Critical assurance gap until it is intentionally modeled and tested.

## Local execution

```bash
cp .env.example .env
composer install
php scripts/assurance/inventory.php
php artisan test tests/Feature/Assurance/InventoryContractTest.php
```

Inventory generation boots Laravel but does not mutate the database.

## Workflow execution modes

`.github/workflows/full-assurance.yml` supports:

- same-repository pull requests to `main`,
- pushes to `main` for exact merged-main evidence,
- manual `workflow_dispatch`,
- a weekly scheduled run.

Slice A initially introduces the `inventory-contract` lane. Subsequent M34 slices add the heavier lanes while Foundation stays unchanged.

## Finding and release policy

The final M34 policy is severity based:

- Critical: release blocked.
- High: release blocked unless a narrow, documented, expiring waiver exists.
- Medium/Low: reported and assigned according to documented ownership.
- Tenant leak, authorization bypass, financial/inventory reconciliation failure, migration failure, duplicate financial/stock effects, restore failure and updater trust-chain failure are always release blockers.

Suppressions may never be blanket ignores. Every suppression/waiver must be scoped to the exact rule and path or CVE and include rationale, owner, expiry and mitigation.

## Troubleshooting

If `inventory-contract` fails:

1. confirm the reported checked-out SHA is the expected PR/main SHA;
2. download the inventory artifact even on failure;
3. distinguish `coverage_status=uncovered` from `trust-boundary-missing`;
4. add a real regression test for a true coverage gap;
5. only classify a new trust boundary when it is an intentional application contract.

Do not change the detector to `|| true`, globally exclude a module, or downgrade a Critical route merely to make CI green.

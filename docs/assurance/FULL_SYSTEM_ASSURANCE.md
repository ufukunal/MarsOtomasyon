# Mars Full System Assurance

M34 adds a release-oriented assurance layer while keeping Foundation focused on three real self-hosted validation jobs: `security`, `quality`, and `browser-smoke`.

## CI validation policy

Foundation executes exactly three real validation jobs:

- `security`
- `quality`
- `browser-smoke`

The repository rule still requires the historical check name `postgres-tests`. That job is therefore retained only as a transparent compatibility gate: it performs no checkout, PostgreSQL setup, migration, or Pest execution and succeeds only when all three real jobs succeed.

`Full Assurance` is report-only. It generates and uploads assurance evidence; it does not execute an additional test suite.

## Trust model

Self-hosted runners must never execute fork pull-request code through `pull_request_target`. Foundation rejects untrusted forks before checkout and validates the exact same-repository PR head SHA. Full Assurance runs on merged `main`, manual dispatches, and its scheduled cadence with read-only repository permissions and exact checkout proof.

A green workflow is not evidence for a different commit. Release evidence records the exact checked-out SHA.

## Slice A: inventory and coverage map

`App\Foundation\Assurance\AssuranceInventory` builds the inventory from the running Laravel application plus the repository filesystem. It does not rely on a manually maintained route count.

Surfaces inventoried include HTTP routes, Artisan/CLI commands, asynchronous jobs/listeners, migration-created data surfaces, external integration boundaries, and operational primitives.

`php scripts/assurance/inventory.php` writes:

- `storage/app/assurance/inventory.json`
- `storage/app/assurance/coverage-map.json`
- `storage/app/assurance/route-authorization-map.json`

Each coverage row records component, type, path/class, state mutation and financial-effect flags, tenant scope, authentication and permission requirements, unit/feature/integration/browser/concurrency/recovery evidence, risk level, and coverage status.

Critical uncovered surfaces remain machine-readable in `critical_gaps`; Slice A intentionally records that coverage debt for later M34 slices instead of suppressing it. An unclassified mutating HTTP trust boundary is a structural inventory blocker and makes inventory generation fail.

## Explicit trust classes

The inventory distinguishes web session authentication, B2B sessions, integration tokens, scanner agents, platform administration, login/password-reset entry points, external webhooks, and scanner enrollment. A new public mutation without an intentionally classified trust boundary is a Critical assurance gap.

## Workflow execution

`.github/workflows/full-assurance.yml` supports pushes to `main`, manual dispatch, and a weekly scheduled run. It produces report artifacts only. The three Foundation jobs are the project test gates.

## Finding and release policy

- Critical: release blocked.
- High: release blocked unless a narrow, documented, expiring waiver exists.
- Medium/Low: reported and assigned according to documented ownership.
- Tenant leak, authorization bypass, financial/inventory reconciliation failure, migration failure, duplicate financial/stock effects, restore failure, and updater trust-chain failure remain release blockers when detected by the supported assurance gates.

Suppressions may never be blanket ignores. Every waiver must be scoped to the exact rule/path or CVE and include rationale, owner, expiry, and mitigation.

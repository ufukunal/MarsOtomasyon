# M34 — Full System Assurance, Security Audit & Functional Verification

Base milestone: M33 Update Center signed-manifest foundation.

## Completion rule

M34 is closed in eight sequential slices. A slice is complete only after its PR is merged with the verified expected head and the exact merged `main` SHA is green for the three real self-hosted Foundation validation jobs:

- `security`
- `quality`
- `browser-smoke`

The historical required status name `postgres-tests` remains only as a compatibility gate. It performs no checkout, PostgreSQL setup, migration, or Pest execution and succeeds only when the three real jobs succeed.

- [ ] Slice A — Assurance inventory & coverage map
- [ ] Slice B — Deep code quality & architecture enforcement
- [ ] Slice C — SAST, secrets & supply-chain security
- [ ] Slice D — Authorization, RBAC & tenant isolation
- [ ] Slice E — Functional accounting/operations verification
- [ ] Slice F — Database, lifecycle & concurrency integrity
- [ ] Slice G — Browser, DAST, resilience & recovery verification
- [ ] Slice H — Unified Full Assurance pipeline, policy & evidence

## Slice A

Inventory HTTP, CLI, async, data/migration, external integration, and operational surfaces. Generate machine-readable coverage and authorization maps. Structural mutating trust-boundary gaps fail closed; unresolved coverage debt remains visible for later slices.

## Slice B

Strengthen static architecture boundaries, layering constraints, forbidden dependency patterns, and maintainability evidence. Execute architecture assurance from `quality`.

## Slice C

Strengthen dependency auditing, secret detection, static security rules, workflow/action integrity checks, and supply-chain evidence. Execute from `security`.

## Slice D

Strengthen authorization and tenant attack-surface checks for session, B2B, API token, scanner, platform-admin, permissions, tenant context, and idempotent writes. Static assurance runs in `security`; runtime user-visible abuse cases run in `browser-smoke`.

## Slice E

Add executable inspection of financial and stock mutation invariants and browser coverage for critical accounting flows. Static invariants run in `quality`; runtime flows remain in `browser-smoke`.

## Slice F

Add migration/schema integrity inspection to `quality` and exercise database lifecycle/concurrency only through the isolated PostgreSQL environment already used by `browser-smoke`. No separate PostgreSQL test lane is introduced.

## Slice G

Strengthen browser security headers, DAST-style assertions, failure/recovery paths, backup/update/recovery controls, and failure injection inside `browser-smoke` with supporting static checks in `security`.

## Slice H

Aggregate machine-readable evidence and three-gate results into a final release report and artifacts. `Full Assurance` is report-only and does not execute an additional test suite.

## Final policy

Critical findings block release. High findings block release unless a narrow, documented, expiring waiver exists. Suppressions must be scoped, owned, expiring, and auditable. No M34 slice introduces a fourth real test job beyond the three Foundation gates.

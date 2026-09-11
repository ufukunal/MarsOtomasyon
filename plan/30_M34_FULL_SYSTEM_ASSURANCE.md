# M34 — Full System Assurance, Security Audit & Functional Verification

Base milestone: M33 Update Center signed-manifest foundation.

## Completion rule

M34 is closed in eight sequential slices. A slice is complete only after its PR is merged with the verified expected head and the exact merged `main` SHA is green for the three real self-hosted Foundation validation jobs:

- `security`
- `quality`
- `browser-smoke`

The historical required status name `postgres-tests` remains only as a compatibility gate. It performs no checkout, PostgreSQL setup, migration, or Pest execution and succeeds only when the three real jobs succeed.

- [x] Slice A — Assurance inventory & coverage map
- [x] Slice B — Deep code quality & architecture enforcement
- [x] Slice C — SAST, secrets & supply-chain security
- [x] Slice D — Authorization, RBAC & tenant isolation
- [x] Slice E — Functional accounting/operations verification
- [x] Slice F — Database, lifecycle & concurrency integrity
- [x] Slice G — Browser, DAST, resilience & recovery verification
- [x] Slice H — Unified Full Assurance pipeline, policy & evidence

## Slice A

Inventory HTTP, CLI, async, data/migration, external integration, and operational surfaces. Generate machine-readable coverage and authorization maps. Structural mutating trust-boundary gaps fail closed; unresolved coverage debt remains visible for later slices.

## Slice B

Strengthen static architecture boundaries, layering constraints, forbidden dependency patterns, and maintainability evidence. Execute architecture assurance from `quality`.

## Slice C

Strengthen dependency auditing, secret detection, static security rules, workflow/action integrity checks, and supply-chain evidence. Execute from `security`.

## Slice D

Strengthen authorization and tenant attack-surface checks for session, B2B, API token, scanner, platform-admin, permissions, tenant context, and idempotent writes. Runtime attack cases execute inside `browser-smoke`.

## Slice E

Execute financial and stock mutation invariants inside `browser-smoke` against isolated PostgreSQL and retain accounting evidence.

## Slice F

Exercise migration/schema lifecycle, PostgreSQL integrity, transaction boundaries, idempotency, query plans, leases, reservations, and concurrency inside the isolated PostgreSQL environment already used by `browser-smoke`. No separate PostgreSQL test lane is introduced.

## Slice G

Exercise readiness, recovery mode and safety, offsite backup readiness, Update Center stability, production-provider kill switches, and the existing Playwright regression suite inside `browser-smoke`.

## Slice H

Aggregate machine-readable evidence from the three Foundation gates into a final release report and artifact. `Full Assurance` is report-only and does not execute an additional test suite.

## Final policy

Critical findings block release. High findings block release unless a narrow, documented, expiring waiver exists. Suppressions must be scoped, owned, expiring, and auditable. No M34 slice introduces a fourth real test job beyond the three Foundation gates.

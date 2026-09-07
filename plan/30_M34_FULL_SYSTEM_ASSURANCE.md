# M34 — Full System Assurance, Security Audit & Functional Verification

Base milestone: M33 Update Center signed-manifest foundation.

## Completion rule

A slice is checked only after its implementation/tests pass on the exact PR head, the PR is merged with the verified expected head, and Foundation plus the applicable Full Assurance jobs pass on the exact merged `main` SHA.

- [ ] Slice A — Assurance inventory & coverage map
- [ ] Slice B — Deep code quality & architecture enforcement
- [ ] Slice C — SAST, secrets & supply-chain security
- [ ] Slice D — Authorization, RBAC & tenant isolation
- [ ] Slice E — Functional accounting/operations verification
- [ ] Slice F — Database, lifecycle & concurrency integrity
- [ ] Slice G — Browser, DAST, resilience & recovery verification
- [ ] Slice H — Unified Full Assurance pipeline, policy & evidence

## Slice A deliverables

- Runtime HTTP/CLI inventory
- Async/data/external/operations inventory
- Machine-readable coverage map
- Route authorization/trust map
- Critical uncovered-surface detector
- Fail-closed CI lane with exact-SHA proof
- Inventory contract regression tests
- Assurance architecture documentation

## Final required lanes

- `inventory-contract`
- `code-quality-deep`
- `sast-supply-chain`
- `authorization-tenancy`
- `functional-regression`
- `database-integrity`
- `concurrency-resilience`
- `browser-dast`
- `assurance-report`

Foundation remains `quality`, `postgres-tests`, `browser-smoke`, `security`.

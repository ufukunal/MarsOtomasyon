# Planning Backlog

## Immediate — FW-IMP-006 Mars.Web + Mars.UI foundation
Status: READY.

Predecessors:
- FW-IMP-001 repository solution skeleton — COMPLETED.
- FW-IMP-002 configuration/context/error primitives — COMPLETED.
- FW-IMP-003 persistence/migration baseline — COMPLETED.
- FW-IMP-004 audit/idempotency/outbox foundations — COMPLETED.
- FW-IMP-005 API foundation implementation — COMPLETED.

FW-IMP-005 evidence:
- canonical report: `docs/plan/01-foundation/fw-imp-005-implementation.md`
- tested implementation commit: `22f31b087f887270bb43f4a39038d7c9a9e07b87`
- successful workflow run: `35722169157`
- 26 / 26 targeted Foundation tests;
- migration scope/model drift, API smoke, OpenAPI generation and project references passed.

Repository-defined FW-IMP-006 package:
- Vite/TypeScript;
- shell/router/API client;
- design tokens;
- Button/Field/Dialog/Tabs/Lookup/Grid baseline.

No FW-IMP-006-specific unresolved owner technology gate is currently identified.

Do not pull these later/deferred choices forward:
- structured logging/metrics stack;
- object/file storage backend;
- optional scheduling library;
- Desktop shell technology;
- Mobile shell technology;
- production secret store;
- production reverse proxy/tunnel details;
- Docker/test deployment baseline.

## Subsequent Foundation implementation sequence
Repository-defined sequence in `docs/plan/01-foundation/framework-plan.md`:
- FW-IMP-007 — Docker/test deployment baseline
- FW-IMP-008 — thin vertical framework proof

Deferred technology gates are resolved only when their relevant implementation step requires them.

## Quality — master planning sequence item 10
Target: docs/plan/08-kalite/
Status: PLANNING BACKLOG / NOT STARTED.

Quality remains the next section-8 item that can raise exact planning completion from 9/30.
Operational execution belongs P6.

## Commerce/B2B/Architect/Marketplace
Target: docs/plan/15-e-ticaret-b2b-api/
Status: P7 / NOT IMMEDIATE.

## Full Test Day
Status: DEFERRED BY POLICY.

Exact section-8 planning progress:
- master: 9 / 30 = 30.0%
- P2: 8 / 8 = 100.0%

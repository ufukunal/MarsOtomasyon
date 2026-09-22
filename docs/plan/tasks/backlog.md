# Planning Backlog

## Immediate — FW-IMP-005 API foundation technology decision-gate resolution
Status: BLOCKED ON OWNER DECISIONS.

Predecessors:
- FW-IMP-001 repository solution skeleton — COMPLETED.
- FW-IMP-002 configuration/context/error primitives — COMPLETED.
- FW-IMP-003 persistence/migration baseline — COMPLETED.
- FW-IMP-004 audit/idempotency/outbox foundations — COMPLETED.

Repository-defined FW-IMP-005 package:
- middleware/pipeline;
- auth integration after provider decision;
- error mapping;
- OpenAPI after tooling decision;
- health/readiness.

Two gates are now relevant and must be explicitly resolved before full FW-IMP-005 implementation:
1. exact authentication/identity provider;
2. exact OpenAPI tooling.

Do not silently select either technology.

Do not pull these still-deferred gates forward:
- structured logging/metrics stack;
- object/file storage backend;
- optional scheduling library;
- Desktop shell technology;
- Mobile shell technology;
- production secret store;
- production reverse proxy/tunnel details.

After both FW-IMP-005 gates are accepted and recorded, FW-IMP-005 implementation may be activated.

## Subsequent Foundation implementation sequence
Repository-defined sequence in `docs/plan/01-foundation/framework-plan.md`:
- FW-IMP-006 — Mars.Web + Mars.UI foundation
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

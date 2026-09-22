# Planning Backlog

## Immediate — FW-IMP-005 API foundation implementation
Status: READY.

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

Resolved technology gates:
1. authentication/identity — ASP.NET Core Identity (.NET 10) + OpenIddict 7.7.1 stable via ADR-0003;
2. OpenAPI — Microsoft.AspNetCore.OpenApi 10.0.12 via ADR-0004.

Do not pull these still-deferred gates forward:
- structured logging/metrics stack;
- object/file storage backend;
- optional scheduling library;
- Desktop shell technology;
- Mobile shell technology;
- production secret store;
- production reverse proxy/tunnel details.

FW-IMP-005 implementation is activated as the immediate Foundation work package.

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

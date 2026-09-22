# Planning Backlog

## Immediate — FW-IMP-007 Docker/test deployment baseline
Status: READY.

Predecessors:
- FW-IMP-001 through FW-IMP-006 — COMPLETED.

FW-IMP-006 evidence:
- canonical report: `docs/plan/01-foundation/fw-imp-006-implementation.md`
- tested implementation commit: `1264981655329099a086c7048c0890caf04dc9f2`
- successful workflow run: `35729371845`
- 10 / 10 frontend targeted tests;
- Vite production build and static architecture checks passed;
- existing .NET build/tests/migration/API gates remain green.

Repository-defined FW-IMP-007 package:
- images/compose;
- migration/startup policy;
- deploy to separate test environment;
- readiness;
- small smoke evidence.

Preflight facts to verify:
- Docker version;
- Docker Compose version;
- deployment/service layout;
- test URL/DNS/TLS facts needed by the chosen route;
- canonical test credential availability without logging values.

Deferred technology gates that must not be silently selected:
- structured logging/metrics stack;
- production secret store;
- production reverse proxy/tunnel details;
- Desktop shell technology;
- Mobile shell technology.

Test deployment decisions remain separate from production architecture.

## Subsequent Foundation implementation sequence
- FW-IMP-008 — thin vertical framework proof

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

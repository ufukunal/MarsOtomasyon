# Planning Backlog

## Immediate — Resolve P5 Parties Create Party Core Identity blockers
Status: BLOCKED DECISION REQUIRED.

Readiness report:
- `docs/plan/03-cariler/p5-first-slice-readiness.md`

Selected first slice:
- Create Party Core Identity.

Resolve only:
1. Mars permission authority/evaluation contract for server-side `party.create`.
2. Initial Party Code assignment authority.
3. Minimum fuzzy duplicate-warning contract or explicit first-slice deferral.
4. Physical Company reference strategy for Party persistence while no Company table exists.

After all four are resolved:
- record the dedicated first Parties implementation work-package ID;
- implement only Create Party Core Identity;
- keep role/tax/contact/address/merge and other modules out of scope.

Do not start implementation by:
- treating authentication as authorization;
- inventing permission tables/claims;
- inventing Party numbering format;
- silently choosing manual Party Code entry;
- inventing fuzzy matching thresholds;
- silently skipping frozen duplicate-warning behavior.

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

Party heavy risks now explicitly include:
- concurrent Party Code allocation;
- deterministic duplicate concurrency;
- stale Party edit;
- cross-company IDOR;
- broad permission matrix;
- browser lifecycle E2E;
- high-volume duplicate-candidate search;
- snapshot persistence integration;
- security/privacy audit.

Exact section-8 planning progress:
- master: 9 / 30 = 30.0%
- P2: 8 / 8 = 100.0%

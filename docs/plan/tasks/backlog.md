# Planning Backlog

## Immediate — P4 Foundation implementation readiness
Status: READY FOR GOVERNANCE DECISIONS.

Why next:
- master-project-plan phase map explicitly places P4 after completed P3;
- P3 logical DB is COMPLETED / FROZEN;
- Foundation implementation is still blocked by unresolved technology decision gates required by the first implementation slice.

Next readiness session:
- classify Foundation decision gates required-now vs deferrable;
- record explicit owner decisions/ADRs for required-now gates;
- do not implement code until those gates are closed.

No new PLAN number is assigned; use phase ID P4.

## P4 — Foundation implementation
Status: BLOCKED UNTIL REQUIRED READINESS GATES CLOSE.

After gate closure:
- create/activate the smallest repository-supported implementation work package;
- implement Foundation thin slice according to accepted framework contract;
- targeted tests only; heavy suites remain deferred.

## Quality — master planning sequence item 10
Target: docs/plan/08-kalite/
Status: PLANNING BACKLOG / NOT STARTED.

Important:
- Quality is the next section-8 item that can raise exact planning completion from 9/30.
- Its conceptual sequence number is not a PLAN task ID.
- Its operational execution belongs P6 Operations, after P4 Foundation and P5 Core application implementation according to the phase map.

## Production / Subcontracting / Import
Status: later section-8 planning items and P6 operational modules.

## Commerce/B2B/Architect/Marketplace
Target: docs/plan/15-e-ticaret-b2b-api/
Status: P7 / NOT IMMEDIATE.

The prior immediate label “PLAN-011 Commerce” is retired as stale sequencing metadata. No completed historical task is renumbered.
Provider capabilities must be verified at implementation time.

## Full Test Day
Status: DEFERRED BY POLICY.

Exact section-8 planning progress:
- master: 9 / 30 = 30.0%
- P2: 8 / 8 = 100.0%

# Planning Backlog

## Next dependency after active INVENTORY-IMP-001 — Sales broad implementation tranche definition
Status: BLOCKED BY ACTIVE INVENTORY IMPLEMENTATION.

Dependency:
- INVENTORY-IMP-001 must establish the Inventory Ledger / Reservation authority and required master/trace contracts first.

Direction when unblocked:
- reconcile frozen PLAN-002 + PLAN-010 against the implemented Inventory contract
- define one broad coherent Sales implementation tranche
- do not assign the Sales implementation package ID before repository reconciliation and scope freeze

## Quality — master planning sequence item 10
Target: docs/plan/08-kalite/
Status: PLANNING BACKLOG / NOT STARTED.

Quality remains the next section-8 planning item that can raise exact planning coverage from 9/30.
Operational execution belongs P6.

## Commerce/B2B/Architect/Marketplace
Target: docs/plan/15-e-ticaret-b2b-api/
Status: P7 / NOT IMMEDIATE.

## Full Test Day
Status: DEFERRED BY POLICY.

Party heavy risks include:
- concurrent Party Code create;
- concurrent role activation/lifecycle transition;
- concurrent deterministic Tax Identity collision;
- concurrent Party deactivation/state mutation;
- stale Party/role/tax identity state;
- cross-company IDOR;
- broad permission matrix;
- authenticated browser Party lifecycle E2E;
- consuming module eligibility races;
- provider reconciliation when introduced;
- high-volume duplicate/identity search;
- PII/security regression;
- snapshot persistence integration.

Exact section-8 planning progress:
- master: 9 / 30 = 30.0%
- P2: 8 / 8 = 100.0%

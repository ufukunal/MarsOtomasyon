# Planning Backlog

## Next dependency after active Sales tranche definition — Purchasing broad implementation tranche definition
Status: BLOCKED BY ACTIVE SALES SCOPE/IMPLEMENTATION.

Dependency:
- the Sales broad tranche must be frozen and implemented according to P5 dependency order before Purchasing becomes current.

Direction when unblocked:
- reconcile frozen PLAN-005 + PLAN-010 against implemented Product/Inventory/Sales contracts
- define one broad coherent Purchasing implementation tranche
- do not assign the Purchasing implementation package ID before repository reconciliation and scope freeze

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

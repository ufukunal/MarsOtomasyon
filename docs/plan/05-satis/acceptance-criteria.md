# PLAN-002 Acceptance Criteria

## 1. Contract coverage

- [x] Quote purpose/effect contract documented.
- [x] Sales Order purpose/effect contract documented.
- [x] Reservation contract documented as non-physical commitment.
- [x] Dispatch physical stock-out recognition documented at POSTED/finalization.
- [x] Invoice financial receivable recognition documented at POSTED/finalization.
- [x] Dispatch-sourced invoice explicitly prohibited from second stock posting.
- [x] Ordered/reserved/shipped/invoiced/returned/remaining planning semantics documented.
- [x] Partial shipment documented.
- [x] Partial invoicing documented.
- [x] Source/target line relations documented.
- [x] State machines documented.
- [x] Cancellation vs reversal documented.
- [x] Posted immutability documented.
- [x] Historical snapshot contract documented.
- [x] Collection documented as a separate financial event.
- [x] Physical return and financial credit/refund separated.
- [x] V38 Sales screen mapping documented.
- [x] Planning-level permissions documented.
- [x] Audit/outbox/idempotency needs documented.
- [x] Report/KPI future contracts documented.
- [x] Full Test Day heavy scenarios documented.
- [x] No SQL/application implementation included.

## 2. Unresolved mandatory owner decisions

PLAN-002 cannot be marked fully frozen/DONE while the following decisions remain open:

- [ ] SALES-B001 — Collection allocation model.
- [ ] SALES-B002 — Source-less/direct Sales Invoice stock behavior.
- [ ] SALES-B003 — Quote full vs partial conversion.
- [ ] SALES-B004 — Reservation trigger policy.
- [ ] SALES-B005 — Cost/COGS recognition point.
- [ ] SALES-B006 — Tax/discount/rounding/FX calculation policy.
- [ ] SALES-B007 — Quote/Order approval policy and thresholds.
- [ ] SALES-B008 — Confirmed-order amendment policy.

## 3. Completion status

Current status: PARTIAL / BLOCKED FOR OWNER DECISIONS.

Reason:
The workflow architecture and known invariants are documented, but implementation would still require guessing material Sales/Finance policies. Repository protocol forbids that.

## 4. Quick verification checklist

Before final PLAN-002 completion:
- all required files exist;
- no file is an empty placeholder;
- effect matrix covers Quote/Order/Reservation/Dispatch/Invoice/Collection/Return/Proforma;
- every transactional object has state/lifecycle contract or explicit external ownership;
- no dispatch-sourced invoice double-stock path exists;
- partial quantity semantics are explicit;
- blocker IDs are consistent across files;
- V38 mapping is present;
- no CREATE TABLE/migration/C#/TypeScript implementation was added;
- main HEAD is re-verified.

## 5. Exit condition

After owner resolves SALES-B001..B008:
1. update affected Sales plan files;
2. remove/close blockers;
3. rerun document consistency checks;
4. mark PLAN-002 completed;
5. update project-state/task/handoff;
6. only then prepare PLAN-003 — Party / Customer / Supplier model.

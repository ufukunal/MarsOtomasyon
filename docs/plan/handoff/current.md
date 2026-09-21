# Current Handoff

## Repository
- Repository: ufukunal/MarsOtomasyon
- Allowed branch: main
- Current HEAD: verify from Git at session start
- Master plan: docs/plan/master-project-plan.md
- Session protocol: docs/ai/session-execution-protocol.md

## Current phase
P2 — Core Commercial Workflow Planning

## Planning progress
Counting basis: master-project-plan section 8; only COMPLETED / FROZEN packages count.
- Master planning sequence: 8 / 30 = 26.7%
- P2 core commercial planning: 7 / 8 = 87.5%

## Completed predecessor
PLAN-008 — Checks / Promissory Notes workflow contract
Status: COMPLETED / FROZEN

Canonical contracts:
- docs/plan/10-cek-senet/README.md
- docs/plan/10-cek-senet/plan.md
- docs/plan/10-cek-senet/workflows.md
- docs/plan/10-cek-senet/forms.md
- docs/plan/10-cek-senet/data-contract.md
- docs/plan/10-cek-senet/permissions.md
- docs/plan/10-cek-senet/integrations.md
- docs/plan/10-cek-senet/reports.md
- docs/plan/10-cek-senet/acceptance-criteria.md
- docs/plan/10-cek-senet/full-test-day.md

## Frozen PLAN-008 decisions
- Checks/Notes owns instrument identity, lifecycle, custody and movement history.
- Finance remains authority for Account/Cash/Bank and instrument monetary positions.
- Incoming accepted receipt: CUSTOMER CREDIT + instrument receivable; Cash/Bank NONE.
- Actual incoming settlement: instrument receivable decrease + Cash/Bank IN; no second Customer credit.
- Outgoing accepted delivery: SUPPLIER DEBIT + instrument payable; Cash/Bank NONE.
- Actual clearing: instrument payable decrease + Cash/Bank OUT; no second Supplier debit.
- Bank handoff is custody-only.
- Whole-remaining-amount endorsement only in core; partial endorsement is forbidden.
- Endorsement to eligible Supplier: SUPPLIER DEBIT, no Cash/Bank.
- Partial collection/payment may occur only against accepted evidence and cannot exceed remaining.
- Bounce/unpaid/return restores the affected Party position by explicit compensation; protest is additional evidence/status, not duplicate posting.
- Original nominal amount/currency and snapshots are immutable.
- Posted history is append/reversal; no Invoice allocation/open-item authority; no STOCK effect.
- FX follows PLAN-007 carrying/realized FX ownership.

No external web research was required.
No SQL, migration, C#/API/TypeScript, deployment or heavy tests were added/run.

## Next safe work package
PLAN-009 — Returns / RMA workflow contract
Target: docs/plan/11-iadeler-rma/

Logical database planning remains blocked until PLAN-009 is sufficiently frozen.
Do not start logical SQL schema, application code or Full Test Day in PLAN-009.

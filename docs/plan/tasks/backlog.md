# Planning Backlog

## PLAN-008 — Checks / Promissory Notes workflow contract
Target: docs/plan/10-cek-senet/

Status: READY / NEXT.

Define incoming/outgoing checks and promissory notes, custody/portfolio, endorsement, bank handoff, collection/payment, bounce/protest/return, partial processing, reversal and exact Account/Cash/Bank recognition points.

PLAN-007 Finance / Treasury dependency is satisfied.

Planning progress:
- master sequence: 7 / 30 = 23.3%
- P2 core commercial: 6 / 8 = 75.0%

Start only in the next dedicated planning work package.

## PLAN-009 — Returns / RMA workflow contract
Target: docs/plan/11-iadeler-rma/

Status: BLOCKED BY PLAN-008.

Freeze customer/supplier returns, RMA authorization/receipt/QC/disposition, physical vs financial correction/refund and source-document traceability.

Must complete after Checks / Promissory Notes and before logical database planning.

## PLAN-010 — Logical database model
Target: docs/db/

Status: BLOCKED BY REMAINING P2 WORKFLOWS.

Begins only after PLAN-008 Checks / Promissory Notes and PLAN-009 Returns / RMA are sufficiently frozen.

## PLAN-011 — Commerce/B2B/Architect/Marketplace planning
Target: docs/plan/15-e-ticaret-b2b-api/

Provider capabilities must be verified before implementation.

## PLAN-012 — Full Test Day plan consolidation
Collect heavy scenarios from all modules. Do not execute until explicitly requested.

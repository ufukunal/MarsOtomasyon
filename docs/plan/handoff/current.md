# Current Handoff

## Repository

- Repository: ufukunal/MarsOtomasyon
- Allowed branch: main
- Current HEAD: verify from Git at session start
- Master plan: docs/plan/master-project-plan.md
- Session protocol: docs/ai/session-execution-protocol.md

## Current phase

P2 — Core Commercial Workflow Planning

## Completed predecessor

PLAN-002 — Sales Domain Workflow Contract

Status: COMPLETED / FROZEN

Primary completion evidence:
- `a5f56208b44847cf74feba43934f6d25412e93ad` — PLAN-002 acceptance criteria completed after Sales owner decisions were frozen.

Sales planning contracts:
- docs/plan/05-satis/README.md
- docs/plan/05-satis/plan.md
- docs/plan/05-satis/workflows.md
- docs/plan/05-satis/forms.md
- docs/plan/05-satis/data-contract.md
- docs/plan/05-satis/permissions.md
- docs/plan/05-satis/integrations.md
- docs/plan/05-satis/reports.md
- docs/plan/05-satis/acceptance-criteria.md
- docs/plan/05-satis/full-test-day.md

No application code, SQL schema/migration, deployment or heavy test was created/run by PLAN-002.

## Frozen Sales decisions

- B001: balance-only customer current account; no Invoice allocation/open-item authority.
- B002: direct/source-less Sales Invoice is financial-only; STOCK = NONE.
- B003: partial/repeated Quote conversion with line/quantity source-target links; all-zero remaining conversion → CONVERTED.
- B004: Reservation is manual explicit action.
- B005: COGS recognized at Sales Invoice POST; physical STOCK OUT remains Dispatch POST.
- B006: KDV-exclusive; line discount → document discount → taxable base; currency-minor-unit rounding; TCMB döviz alış default FX with audited approval-triggering override; posted calculations immutable.
- B007: conditional commercial-policy exception approval; creator != approver.
- B008: audited controlled-delta/versioned amendment over unprocessed scope.

Locked invariants remain:
- Quote no RES/STOCK/ACCOUNT/CASH posting.
- Sales Order no STOCK/ACCOUNT posting.
- Reservation non-physical.
- Dispatch POST normal physical STOCK OUT.
- Invoice POST receivable + financial COGS; no STOCK.
- Collection separate Finance balance event.
- posted history reversed/compensated, never silently rewritten.
- PostgreSQL/ledgers authoritative; Valkey not business truth.

## Next safe work package

PLAN-003 — Party / Customer / Supplier model

Target:
`docs/plan/03-cariler/`

PLAN-003 is READY but content work has not started in this handoff.

Before work:
- verify real main HEAD;
- read governance/master/state/handoff;
- read frozen Sales contracts because Party snapshots/account relations are downstream dependencies;
- inspect current 03-cariler files;
- route skills through skill-router;
- produce CONTEXT RECEIPT.

Do not jump to SQL schema, application code, Purchasing or Full Test Day.

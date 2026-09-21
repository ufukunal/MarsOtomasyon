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

PLAN-005 — Purchasing workflow contract

Status: COMPLETED / FROZEN

Primary completion evidence:
- `a8ef551310d910bcc38c8a383f2a5d86839621d1` — PLAN-005 acceptance criteria completed.

Purchasing planning contracts:
- docs/plan/07-satinalma/README.md
- docs/plan/07-satinalma/plan.md
- docs/plan/07-satinalma/workflows.md
- docs/plan/07-satinalma/forms.md
- docs/plan/07-satinalma/data-contract.md
- docs/plan/07-satinalma/permissions.md
- docs/plan/07-satinalma/integrations.md
- docs/plan/07-satinalma/reports.md
- docs/plan/07-satinalma/acceptance-criteria.md
- docs/plan/07-satinalma/full-test-day.md

No SQL schema/migration, application code, deployment or heavy tests were created/run by PLAN-005.

## Frozen Purchasing decisions

- Purchase Order is commitment only: no STOCK, payable or CASH/BANK effect.
- conditional Purchasing approval applies to commercial/tolerance exceptions; creator != approver.
- partial/short Goods Receipt is allowed.
- over-receipt default BLOCK; configured tolerance use requires exception approval.
- stockable Goods Receipt POST is physical STOCK IN and enters QUARANTINE.
- AVAILABLE release is a separate QC/disposition effect.
- Goods Receipt alone creates no supplier payable.
- Supplier Invoice POST creates payable and STOCK = NONE in every source mode.
- STOCKABLE goods require posted Receipt + 3-way match before normal Supplier Invoice POST.
- SERVICE/NON-STOCK PO invoice uses 2-way match.
- direct/source-less Supplier Invoice is controlled financial-only SERVICE/NON-STOCK flow and requires approval.
- over-invoice default BLOCK.
- price variance default tolerance is zero; configured non-zero accepted variance requires approval.
- KDV/discount/rounding and FX follow the frozen Mars project central convention.
- Payment is Finance-owned and Purchasing does not own settlement/allocation.
- Purchase Return physical STOCK OUT and financial supplier adjustment are separate linked effects.
- posted PO/Receipt/Invoice history is never silently rewritten.

## V38 reference used

Repository HTML showed:
- Satınalma Siparişleri
- Mal Kabul
- Alış Faturaları
- 3-Way Match
- Alış İadeleri
- Tedarikçi Performansı

and explicit notes:
- over-receipt/over-invoice default BLOCK with explicit tolerance policy/approval;
- physical receipt posts to quarantine, usable release after QC/disposition.

No external web research was required for PLAN-005.

## Next safe work package

PLAN-006 — Warehouse operational contract

Target:
`docs/plan/06-ambar-depo/`

PLAN-006 is READY but content work has not started.

Before work:
- verify real main HEAD;
- read governance/master/state/handoff;
- read frozen Sales, Party, Product/Inventory and Purchasing contracts;
- inspect current Warehouse planning files;
- route skills;
- use repository V38 HTML as the default product reference;
- produce CONTEXT RECEIPT.

Do not jump to Finance implementation, logical SQL schema, application code or Full Test Day.

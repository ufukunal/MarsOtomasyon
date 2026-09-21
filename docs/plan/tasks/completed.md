# Completed Tasks

## PLAN-001 — Planning backbone and Foundation plan
**Status:** COMPLETED

Created:
- `docs/plan/project-state.yaml`
- `docs/plan/active-task.yaml`
- `docs/plan/planning-standard.md`
- `docs/plan/roadmap.md`
- `docs/plan/handoff/current.md`
- task tracking files
- decisions structure
- Foundation plan and acceptance criteria
- initial database domain dictionary and design principles

Evidence commit:
- `3a36a50a2978a7307ff395f79f00497ccc2ed59b`

## Governance groundwork
- Single allowed branch established: `main`
- New branch creation blocked at repository level
- PR requirement removed for direct-main workflow
- AI command protocol created
- AI skill system created and detailed
- Skill router created
- Mandatory NEXT PROMPT/autocomplete protocol created

This file records planning milestones, not application implementation.


## FRAMEWORK-001 — Foundation / Framework contract
**Status:** COMPLETED (PLANNING ONLY)

Created:
- `docs/plan/master-project-plan.md`
- `docs/plan/01-foundation/framework-plan.md`
- `docs/ai/session-execution-protocol.md`

Updated:
- `docs/ai/autocomplete.md`
- `docs/plan/ai-cmd.md`
- project state/handoff records

Locked:
- V38 remains product/UI reference, not production codebase.
- Foundation owns shared infrastructure, not domain policy.
- Modular-monolith dependency/transaction/API/UI framework boundaries are documented.
- Every session must produce SESSION REPORT and role-driven standalone NEXT PROMPT.

Not implemented:
- no application source code
- no domain SQL schema
- no framework runtime implementation

Evidence:
- master/framework/protocol commits are recorded in main history.

## PLAN-002 — Sales Domain Workflow Contract
**Status:** COMPLETED / FROZEN (PLANNING ONLY)

Frozen:
- Quote → partial/repeated Order conversion.
- Manual Reservation.
- Dispatch-only physical STOCK OUT.
- Invoice receivable + COGS; Invoice STOCK = NONE.
- Balance-only Collection without Invoice allocation.
- KDV-exclusive calculation sequence and deterministic rounding/FX snapshots.
- Conditional commercial-policy approval with creator != approver.
- Controlled-delta confirmed-order amendment with immutable processed history.

Planning outputs:
- `docs/plan/05-satis/README.md`
- `docs/plan/05-satis/plan.md`
- `docs/plan/05-satis/workflows.md`
- `docs/plan/05-satis/forms.md`
- `docs/plan/05-satis/data-contract.md`
- `docs/plan/05-satis/permissions.md`
- `docs/plan/05-satis/integrations.md`
- `docs/plan/05-satis/reports.md`
- `docs/plan/05-satis/acceptance-criteria.md`
- `docs/plan/05-satis/full-test-day.md`

Acceptance evidence:
- `a5f56208b44847cf74feba43934f6d25412e93ad`

Not implemented:
- no SQL/migration
- no C#/API
- no TypeScript/UI
- no deployment
- Full Test Day pending

## PLAN-003 — Party / Customer / Supplier model
**Status:** COMPLETED / FROZEN (PLANNING ONLY)

Frozen:
- one company-scoped Party master;
- PERSON / ORGANIZATION kinds;
- CUSTOMER and SUPPLIER multi-role model;
- legal/display identity, tax identity, contact and address ownership;
- Party Code role-neutral identity with Settings-owned numbering format;
- ACTIVE / INACTIVE / MERGED lifecycle;
- deterministic identity collision + warning-only fuzzy duplicate detection;
- logical audited merge with survivor/source lineage;
- Finance-owned balances, credit/risk/hold and settlement;
- historical document snapshot immutability;
- no automatic customer/supplier balance netting;
- no cross-company Party sharing.

Planning outputs:
- `docs/plan/03-cariler/README.md`
- `docs/plan/03-cariler/plan.md`
- `docs/plan/03-cariler/workflows.md`
- `docs/plan/03-cariler/forms.md`
- `docs/plan/03-cariler/data-contract.md`
- `docs/plan/03-cariler/permissions.md`
- `docs/plan/03-cariler/integrations.md`
- `docs/plan/03-cariler/reports.md`
- `docs/plan/03-cariler/acceptance-criteria.md`
- `docs/plan/03-cariler/full-test-day.md`

Acceptance evidence:
- `52628746f484919f370feae5cf7be3ab6c87d8ef`

Not implemented:
- no SQL/migration
- no C#/API
- no TypeScript/UI
- no deployment
- Full Test Day pending

## PLAN-004 — Product / Inventory master model
**Status:** COMPLETED / FROZEN (PLANNING ONLY)

Frozen:
- company-scoped Product master;
- GOODS / SERVICE distinction;
- SELLABLE / PURCHASABLE / STOCKABLE capabilities;
- optional Variant identity;
- deterministic Base/alternate UOM conversions;
- unambiguous barcode/GTIN mapping;
- normalized category/classification;
- company-scoped Warehouse and hierarchical Location masters;
- Inventory Ledger as physical quantity authority;
- non-physical Reservation separate from physical disposition;
- explicit on-hand / available / reserved / available-to-reserve semantics;
- NONE / LOT / SERIAL / LOT_SERIAL tracking;
- immutable historical Product/UOM snapshots;
- Finance-owned inventory valuation/cost policy.

Planning outputs:
- `docs/plan/04-urun-stok/README.md`
- `docs/plan/04-urun-stok/plan.md`
- `docs/plan/04-urun-stok/workflows.md`
- `docs/plan/04-urun-stok/forms.md`
- `docs/plan/04-urun-stok/data-contract.md`
- `docs/plan/04-urun-stok/permissions.md`
- `docs/plan/04-urun-stok/integrations.md`
- `docs/plan/04-urun-stok/reports.md`
- `docs/plan/04-urun-stok/acceptance-criteria.md`
- `docs/plan/04-urun-stok/full-test-day.md`

Acceptance evidence:
- `96c1855f200512d50009d1499aeaf7e4f96d88bb`

Not implemented:
- no SQL/migration
- no C#/API
- no TypeScript/UI
- no deployment
- Full Test Day pending

## PLAN-005 — Purchasing workflow contract
**Status:** COMPLETED / FROZEN (PLANNING ONLY)

Frozen:
- Purchase Order commitment only; no stock/payable.
- conditional exception-based approval with creator != approver.
- partial/short Goods Receipt.
- default-block over-receipt with explicit tolerance policy/approval.
- stockable Goods Receipt POST → STOCK IN → QUARANTINE.
- separate QC/disposition release to AVAILABLE.
- Goods Receipt no supplier payable.
- Supplier Invoice POST → payable; STOCK = NONE.
- stockable 3-way PO/Receipt/Invoice match.
- service/non-stock 2-way match.
- controlled direct service/non-stock financial-only invoice.
- default-block over-invoice and zero-default price variance tolerance.
- Purchasing calculation/FX aligned with frozen Mars central policy.
- Finance-owned Payment/settlement.
- physical Purchase Return vs financial supplier adjustment separation.
- immutable posted history/reversal model.

Planning outputs:
- `docs/plan/07-satinalma/README.md`
- `docs/plan/07-satinalma/plan.md`
- `docs/plan/07-satinalma/workflows.md`
- `docs/plan/07-satinalma/forms.md`
- `docs/plan/07-satinalma/data-contract.md`
- `docs/plan/07-satinalma/permissions.md`
- `docs/plan/07-satinalma/integrations.md`
- `docs/plan/07-satinalma/reports.md`
- `docs/plan/07-satinalma/acceptance-criteria.md`
- `docs/plan/07-satinalma/full-test-day.md`

Acceptance evidence:
- `a8ef551310d910bcc38c8a383f2a5d86839621d1`

Not implemented:
- no SQL/migration
- no C#/API
- no TypeScript/UI
- no deployment
- Full Test Day pending

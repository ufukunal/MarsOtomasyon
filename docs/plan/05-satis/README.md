# Sales Module Plan

Status: PLAN-002 COMPLETED / FROZEN — owner decisions resolved and planning contract accepted.

## Purpose

Sales owns the commercial flow from customer intent to ordered demand, reservation linkage, physical dispatch linkage, financial invoicing linkage, collection linkage and sales-return linkage.

Canonical chain:

Quote → Sales Order → Reservation → Dispatch → Sales Invoice → Collection Link → Sales Return Link

Proforma is an auxiliary informational document sourced from Quote or Sales Order.

## Process owners

- Sales: Quote, Sales Order, commercial terms, customer communication and controlled amendments.
- Inventory/Warehouse: authoritative Reservation records and physical inventory movement.
- Warehouse/Shipping: Dispatch preparation/posting and carrier handoff.
- Finance/Accounting: Sales Invoice posting, customer receivable, COGS recognition, collection and financial reversal.
- Returns/Quality: physical return receipt/disposition; full RMA workflow is planned separately.

No UI screen or cache becomes owner of another module's authoritative state.

## Frozen Sales decisions

- B001 — Collection is balance-only current-account settlement; no invoice allocation/open-item model.
- B002 — Direct/source-less Sales Invoice is financial-only; it never posts STOCK OUT.
- B003 — Quote supports line/quantity partial and repeated conversion; Quote becomes CONVERTED when all line remaining conversion quantities are zero.
- B004 — Reservation is explicit/manual from eligible Sales Order context.
- B005 — financial COGS recognition occurs at Sales Invoice POST; physical STOCK OUT remains Dispatch POST.
- B006 — prices are KDV-exclusive; line discount → document discount → taxable base; deterministic currency-minor-unit rounding; default FX is TCMB döviz alış with audited controlled override; posted calculation snapshots are immutable.
- B007 — approval is conditional on commercial-policy exceptions; creator cannot approve own document.
- B008 — confirmed Sales Order changes use audited controlled delta/versioning over unprocessed scope only.

## Locked invariants

- Quote creates no RES/STOCK/ACCOUNT/CASH-BANK posting.
- Sales Order is not physical stock-out and does not create receivable.
- Reservation is non-physical commitment.
- Dispatch POST/finalization is the normal physical STOCK OUT point.
- Dispatch consumes/releases related reservation quantity.
- Sales Invoice POST/finalization creates customer receivable.
- Dispatch-sourced or direct Invoice never posts a second STOCK OUT.
- Collection is a separate Finance event.
- Physical return and financial credit/refund are separate.
- Posted history is corrected by reversal/compensation, never silent mutation.
- Partial shipment/invoicing and Quote conversion preserve line-level source-target traceability.
- PostgreSQL/ledgers are authoritative; Valkey is not stock/accounting truth.

## V38 reference policy

Canonical UI reference:
`docs/reference/ui/marsotomasyon_ui_v38_cari_bakiye_sadelestirildi.html`

V38 supplies product/UX evidence, not production architecture. Preserve list-first Sales navigation, revision/conversion visibility, reservation/dispatch/invoice actions, ordered/processed/remaining quantity visibility, and read-only posted-document correction flows.

## Files

- plan.md — frozen owner decisions and overall contract.
- workflows.md — states, effect matrix, quantities, source-target, amendment/reversal.
- forms.md — UI/form behavior.
- data-contract.md — conceptual entities, authority and snapshots.
- permissions.md — permissions, approval and SoD.
- integrations.md — outbox/external side-effect contracts.
- reports.md — reporting semantics.
- acceptance-criteria.md — PLAN-002 completion evidence.
- full-test-day.md — deferred heavy-test backlog.

## Out of scope

PLAN-002 does not create SQL schema/migrations, C#/API, TypeScript UI, provider adapters, deployment changes or full Returns/Purchasing implementation.


## P5 implementation status

### SALES-IMP-001 — Sales Commercial & Fulfillment Authority Tranche
Status: READY FOR IMPLEMENTATION.

Canonical readiness:
- docs/plan/05-satis/p5-sales-commercial-fulfillment-readiness.md

Broad scope:
- Quote/revision/line authority
- partial/repeated Quote conversion
- Sales Order/effective version/amendment authority
- approval evidence/SoD
- explicit Inventory Reservation integration
- Warehouse resource-scope authorization for Dispatch
- Dispatch POST/reversal integrated with Inventory
- Sales Invoice DRAFT/source/calculation commercial authority
- optional Proforma within the same package
- protected API/Mars.Web/read projections
- additive migration and TEST deployment

Boundary:
- Sales Invoice POST/REVERSE remains outside SALES-IMP-001 until Finance Account/Valuation/Dispatch Cost Bridge authority exists
- Collection/settlement remains Finance-owned
- Pick/Pack/Stage/Load remains Warehouse-owned
- no Sales-owned Reservation or physical stock authority
- no TCMB/e-document/provider implementation
- no production deployment or Full Test Day

Current migration baseline before Sales implementation:
- 8 committed/deployed migrations

Latest predecessor runtime-tested SHA:
- edcc24f1200b70aad102fc510ad7bec60c4515e7

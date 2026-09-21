# Sales Module Plan

Status: PLAN-002 IN PROGRESS — business contracts documented; owner decision gates remain.

## Purpose

Sales owns the commercial flow from customer intent to ordered demand, reservation linkage, physical dispatch linkage, financial invoicing linkage, collection linkage and sales-return linkage.

Canonical chain:

Quote → Sales Order → Reservation → Dispatch → Sales Invoice → Collection Link → Sales Return Link

Proforma is an auxiliary informational document sourced from Quote or Sales Order.

## Process owners

- Sales: quote, sales order, commercial terms, customer communication.
- Warehouse/Shipping: reservation execution visibility, picking/packing, dispatch posting and carrier handoff.
- Finance/Accounting: sales invoice posting, customer receivable, collection and financial reversal.
- Returns/Quality: physical return receipt/disposition; full RMA workflow is planned separately.

No single UI screen becomes owner of another module's authoritative state.

## Locked source-backed rules

- Quote creates no stock movement, reservation or customer receivable.
- Sales Order is not a physical stock-out and does not create receivable.
- Reservation is a commitment against available inventory, not physical movement.
- Dispatch posting/finalization is the normal physical STOCK OUT point.
- Dispatch reduces/releases related reservation quantity.
- Sales Invoice posting/finalization creates customer receivable.
- An invoice sourced from posted dispatch cannot reduce stock a second time.
- Collection is a separate financial event; it is not embedded in invoice posting.
- Physical sales return and financial credit/refund are separate concerns.
- Posted history is not silently edited or deleted; correction uses reversal/compensating actions.
- Partial shipment and partial invoicing must be supported and source-line traceability preserved.
- PostgreSQL will be authoritative; ledger history is authoritative; Valkey is not accounting/inventory truth.

## V38 reference policy

Canonical UI reference:
docs/reference/ui/marsotomasyon_ui_v38_cari_bakiye_sadelestirildi.html

V38 supplies validated product/UX evidence, not production code architecture. Relevant V38 evidence includes:
- list-first Sales menu: Teklifler, Satış Siparişleri, İrsaliye / Sevkiyat, Satış Faturaları, Proforma Faturalar;
- Quote revision/approval/customer-review/order-conversion actions;
- Sales Order reservation, dispatch, invoice, cancel-remaining and close actions;
- Dispatch UI showing ordered / previously shipped / this dispatch / remaining and stating stock leaves only on dispatch finalization;
- Sales Invoice finalization, balance effect, e-document and correction/reversal concepts;
- V38 balance-based current-account simplification removing open_items and settlement_workspace from the active menu;
- Sales Returns routed to a canonical Returns Center;
- Sales reports routed to the canonical report center.

## Files

- plan.md — scope, business decisions, decision gates and overall contract.
- workflows.md — state machines, effect matrix, quantity flow, source/target links.
- forms.md — V38 UI mapping and planned screen/form behavior.
- data-contract.md — conceptual entities, ownership, snapshots and authoritative/derived data.
- permissions.md — roles, permissions and scope requirements.
- integrations.md — outbox/external side-effect contracts.
- reports.md — report/KPI contracts and future formula gates.
- acceptance-criteria.md — PLAN-002 completion criteria and current status.
- full-test-day.md — deferred heavy test backlog.

## Out of scope

This PLAN-002 task does not create:
- SQL schema or migrations,
- C# entities/handlers/endpoints,
- TypeScript/Vite implementation,
- provider adapters,
- full RMA implementation,
- Purchase workflow.

## Current owner-decision gates

See plan.md. The unresolved items are explicit implementation blockers, not invitations for convention-based guessing.

# Logical Entity Catalog

Status: FROZEN — PLAN-010. Names are conceptual, not physical table names.

## Foundation
- Company — organizational authority boundary.
- Branch — optional operational/accounting scope.
- Idempotency Operation — durable logical command identity/result link.
- Outbox Message — pending/published asynchronous event evidence.
- Approval Decision — exact snapshot/version/action approval evidence.
- Audit Event — actor/scope/action/reason/correlation evidence.

## Parties
- Party — company-scoped PERSON/ORGANIZATION.
- Party Role — CUSTOMER/SUPPLIER participation.
- Tax Identity — structured jurisdiction/scheme/value.
- Contact Person — Party-associated operational person.
- Communication Point — phone/email/etc.
- Address — structured Party address + purpose.
- Party External Mapping — provider/system identity mapping.
- Party Merge Lineage — merged source → survivor history.

## Product
- Product — company goods/service master.
- Variant — optional Product child identity.
- UOM — controlled measurement unit.
- Product UOM — Product/Variant applicability + base conversion.
- Barcode Mapping — company/namespace code → Product/Variant/UOM.
- Category / Product Category — normalized classification.
- Product External Mapping — provider/supplier/channel mapping.

## Inventory / Warehouse
- Warehouse — company physical facility.
- Location — Warehouse hierarchy node.
- Inventory Disposition — controlled physical condition.
- Lot — Product/Variant batch identity.
- Serial — individual traceability identity.
- Reservation — source-linked non-physical commitment.
- Reservation Movement/History — create/consume/release lineage if separate records are required; current state never replaces authoritative history.
- Inventory Movement — append-oriented physical quantity effect.
- Warehouse Transfer / Line — requested/issued/received/loss lineage.
- Stock Count Session / Line / Observation — snapshot/recount/adjustment evidence.
- Warehouse Work records — Pick/Pack/Stage/Load/Put-away/Replenishment where required; operational, not stock authority.
- Package / Package Item — shipment packing topology.
- Offline Operation — durable scan operation identity/conflict result.

## Sales
- Quote / Quote Revision / Quote Line.
- Quote Conversion Link — revision line → Sales Order line + quantity.
- Sales Order / Order Version / Order Line / Amendment Delta.
- Dispatch / Dispatch Line.
- Sales Invoice / Invoice Line.
- Sales Source Link — exact Order/Dispatch source relation where a subtype-specific link is not enough.
- Commercial Calculation Snapshot — tax/discount/FX line/header historical values where implementation benefits from normalized snapshot subrecords.

## Purchasing
- Purchase Order / version / line.
- Goods Receipt / line.
- Supplier Invoice / line.
- Match Record / Match Exception Approval — PO/Receipt/Invoice or PO/Invoice matching evidence.
- Purchasing Source Link — quantity/value-bearing exact source relation.

## Finance
- Finance Transaction — business transaction header/state/idempotency/reversal.
- Account Ledger Entry.
- Cash Account / Cash Ledger Entry.
- Bank Account / Bank Ledger Entry.
- Role Netting Detail.
- Posting Period.
- FX Carrying/Revaluation Record.
- Treasury Transfer Detail.
- Bank Statement Batch / Statement Line.
- Reconciliation Match / Match Detail.
- Credit/Risk Policy.
- Inventory Valuation Pool Identity.
- Inventory Valuation Entry.
- Dispatch Cost Bridge Portion / Cost Allocation relation where needed to preserve source quantities/value consumption.

Advance and current balance are derived from ledger entries; no separate mutable authority entity is introduced.

## Checks / Notes
- Instrument.
- Instrument Movement.
- Instrument Source/Target Link.
- Instrument Financial Position Reference.
Custody/lifecycle remain separate from Finance monetary position.

## Returns
- Return Case.
- Return Line.
- Return Source Link.
- Physical Processing Reference.
- Financial Processing Reference.
- Replacement Link.
- Source-less Exception Evidence/Approval reference.

## Read models / projections
Examples only, rebuildable:
- Party Search.
- Stock Balance / Availability.
- Reservation availability.
- Customer/Supplier role balances.
- Aging.
- Risk exposure.
- Sales/Purchase document progress.
- Return progress.
- Instrument maturity/portfolio.
- Warehouse work queues.
- Dashboard/KPI projections.

## Authority rule
A projection or work-state record cannot be promoted to authoritative physical/financial truth merely because it is convenient for a UI query.

# Database Domain Dictionary — v2

Status: COMPLETED / FROZEN — PLAN-010 logical model vocabulary.

This dictionary is derived from frozen PLAN-002 through PLAN-009 workflows. It defines logical meaning, not physical SQL names.

## Core scope terms

### Company
Primary business authority boundary for commercial, inventory and finance records. Cross-company transactional/source-target references are forbidden unless a future explicit contract says otherwise.

### Branch
Operational/accounting scope used only where the owning workflow requires it. Branch is not automatically copied onto every entity.

### Party
One company-scoped person or organization that may hold multiple business roles. It is not split into separate Customer/Supplier identities.

### Party Role
A typed participation of Party. Frozen core roles are CUSTOMER and SUPPLIER. Role state is independent from Party state.

### Financial Role
Finance view of Party balance semantics:
- CUSTOMER_RECEIVABLE;
- SUPPLIER_PAYABLE.
It is distinct from Party master role while referencing the same Party identity.

### Product
Company-scoped goods/service master.

### Variant
Optional operationally meaningful child identity of Product.

### UOM
Controlled unit of measure. Product-UOM relation defines positive decimal conversion to Base UOM and supplies historical conversion snapshots.

### Warehouse
Company-scoped inventory facility.

### Location
Position within one Warehouse. Location answers where; disposition answers condition.

### Inventory Disposition
Physical stock condition. Frozen core values include AVAILABLE, QUARANTINE, QUALITY_HOLD, REWORK, DAMAGED and TRANSIT. Reservation is not a disposition.

### Lot
Traceability identity for batch-tracked stock. Quantity derives from Inventory Ledger.

### Serial
Individual traceability identity. Current physical position derives from Inventory Ledger; one serial cannot have simultaneous authoritative positions.

### Reservation
Inventory-owned non-physical commitment against eligible stock. It changes available-to-reserve, not on-hand.

## Commercial terms

### Commercial Document
A bounded-context business document such as Quote, Sales Order, Dispatch, Sales Invoice, Purchase Order, Goods Receipt or Supplier Invoice. PLAN-010 does not create one nullable universal document authority.

### Document Version / Revision
Immutable or append-oriented historical version of a document where the frozen workflow requires revision/amendment history.

### Source-Target Link
Normalized relationship connecting exact source and target records/lines/versions with explicit quantity or value basis where required. Remaining values are derived from authoritative source and link history.

### Return / RMA
Returns-owned case/line authorization and coordination context linking source documents, physical Inventory processing, Finance corrections/refunds and replacement Sales flow without owning those ledgers.

## Ledger and finance terms

### Ledger
Append-oriented authoritative record of posted business effects. Core authoritative families:
- Inventory Ledger;
- Account Ledger;
- Cash Ledger;
- Bank Ledger;
- Inventory Valuation / Cost Ledger.

These families are not collapsed into one generic ledger because dimensions and invariants differ.

### Inventory Ledger
Authoritative physical quantity movement history by Product/Variant, warehouse/location/disposition and lot/serial where applicable.

### Account Ledger
Authoritative Party financial-balance history by company + Party + financial role + currency.

### Cash Ledger
Authoritative Cash Account money movement history.

### Bank Ledger
Authoritative Bank Account book movement history.

### Inventory Valuation Pool
Finance-owned company + Product/Variant + Base UOM valuation grain using frozen perpetual moving weighted-average policy.

### Inventory Valuation Entry
Append-oriented value/cost effect linked to physical/commercial sources. It never becomes physical quantity authority.

### Dispatch Cost Bridge
Finance-owned source-linked carrying value removed at Dispatch but not yet recognized as COGS until eligible Sales Invoice posting.

### Finance Transaction
Finance-owned business transaction coordinating one or more exact Account/Cash/Bank/valuation effects such as Collection, Payment, Refund, Transfer, Netting, Revaluation or Financial Correction.

### Posting Period
Finance period control with OPEN / FROZEN / CLOSED states.

### Reconciliation
Explicit matching evidence between imported Bank Statement lines and posted Bank Ledger entries. Imported statement data is not Bank Ledger truth.

### Advance Position
Opposite-sign Party role balance produced by ledger entries; not a mutable separate balance authority and not invoice allocation.

## Checks / Notes

### Instrument
Accepted Check or Promissory Note identity with immutable nominal/currency/date/core snapshot.

### Instrument Movement
Append-oriented lifecycle/custody action over an Instrument, including receipt, bank handoff, endorsement, settlement, bounce/protest/return and reversal.

### Instrument Financial Position
Finance-owned monetary receivable/payable position referenced by instrument movements. Custody and financial state remain separate.

## Foundation / history terms

### Snapshot
Intentional historical denormalization preserving legally/operationally accepted Party, Product/UOM, tax, FX, source-version or instrument values after live master changes.

### Projection
Rebuildable read-optimized state derived from authoritative records. Projection is never business truth.

### Posting
Accepted action that creates an authoritative document/ledger effect.

### Reversal
New compensating record linked to original posted effect. It never deletes/re-writes the original.

### Idempotency Identity
Durable logical operation identity preventing a retry from creating duplicate authoritative effects.

### Outbox
Durable asynchronous event record written in the same PostgreSQL transaction as the business change that produced it.

### Approval
Decision evidence binding an exact version/snapshot/amount/quantity/action. Where SoD applies, initiator cannot approve own action.

### Audit
Security/operational record of actor, time, entity, action, scope, reason and correlation. Audit is not a duplicate business ledger.

## Rule
If a physical schema detail is not required to preserve these accepted meanings, PLAN-010 leaves it for physical schema/migration implementation rather than guessing it.

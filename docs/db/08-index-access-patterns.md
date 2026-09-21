# Logical Index and Access-Pattern Intent

Status: FROZEN — PLAN-010. No physical CREATE INDEX statements.

Indexes are selected from access patterns and constraints; write cost and cardinality must be measured during implementation.

## Master lookup
- Party: company + Party Code; active tax identity; normalized searchable name/contact projections.
- Product: company + Product/SKU code; active barcode namespace/value.
- Warehouse/Location: company/Warehouse + code.
- Lot/Serial: company + Product/Variant + lot/serial identity.

## Source-target traversal
Support efficient lookup by:
- exact source document/line/version → target links;
- exact target → source;
- source + state to calculate eligible remainder;
- original effect → reversal/compensation.

## Inventory
Projection inputs commonly filter/order by:
company, Product/Variant, Warehouse, Location, disposition, Lot, Serial, posting sequence/time.
Source lookup by source document/line/movement is required.
Reservation queues require source line and company/Product/Warehouse eligibility access.

## Commercial
Work queues commonly use company + state + date and Party/document number.
Quote/Order/Dispatch/Invoice and PO/Receipt/Invoice navigation requires exact source keys.
Document number lookup follows configured company/branch/type scope.

## Finance
Account Ledger: company + Party + financial role + currency + posting/due date.
Cash/Bank Ledger: owning account + posting/value date.
Finance Transaction: company + kind/state/date and durable idempotency key.
Posting Period: company + date range/state lookup.
Valuation: company + Product/Variant + posting sequence/source.
Bridge: Dispatch source and Invoice consumption lookup.

## Bank statement/reconciliation
Statement dedupe: account/provider + stable external ID where available; fingerprint candidate lookup otherwise.
Reconciliation: statement-line remaining and Bank-entry unreconciled lookup.
Pending/unmatched queues by Bank Account/state/date.

## Checks / Notes
Instrument lookup by company/type/direction/reference plus bank/issuer context.
Maturity/portfolio queue by company/custody/lifecycle/maturity.
Movement lookup by Instrument + sequence/time and external settlement/idempotency identity.

## Returns
Pending queues by company/direction/state/warehouse/age.
Source link lookup by Sales Dispatch/Goods Receipt source.
Physical/financial processing references by Return Line and owning movement/transaction.

## Foundation
Idempotency operation lookup by scope + operation key.
Outbox pending delivery by state/available time/order.
Audit by entity/public identity, actor/time/correlation as operationally required.

## Rule
A logical access-pattern entry is not permission to create every possible composite index. Physical indexes require actual query shape and EXPLAIN/write-cost review.

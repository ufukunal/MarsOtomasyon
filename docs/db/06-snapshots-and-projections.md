# Historical Snapshots and Projections

Status: FROZEN — PLAN-010.

## 1. Snapshot principle
Historical snapshots intentionally duplicate accepted values so later master edits do not alter past legal/operational meaning.

Snapshot is owned by the consuming document/effect context, not by a generic live master copy.

## 2. Party snapshots
As required by document:
Party Code visible at event, legal/display name, tax identity, billing/shipping address, contact/recipient and jurisdiction-relevant legal fields.

Master Party merge/deactivate/edit never rewrites posted snapshots.

## 3. Product/UOM snapshots
Product/Variant code/name, UOM code/name, accepted conversion factor/direction, barcode/GTIN when operationally required, lot/serial evidence where appropriate.

Live UOM conversion/tracking changes do not rewrite historical transaction basis.

## 4. Commercial/tax snapshots
Posted Sales/Supplier Invoice preserves quantity, price, discounts, taxable base, tax rate/treatment, line tax, totals, currency minor-unit/rounding result, accepted source identity/version.

## 5. FX snapshots
Posted commercial/Finance transactions preserve currency, base value, rate source/type/date/rate and override evidence where applicable.
Later rates do not mutate original.

## 6. Instrument snapshots
Accepted instrument retains issuer/payee/Party/bank/reference/currency/nominal/dates and custody evidence required for history even if live masters change.

## 7. Return snapshots
Return authorization preserves Party, Product/UOM/conversion, source identity/version, reason/condition and relevant lot/serial evidence.
Source-less cases preserve exception evidence rather than fabricating source history.

## 8. Rebuildable projections
Allowed:
- stock on-hand by company/Product/Warehouse/Location/disposition/lot/serial;
- available stock and available-to-reserve;
- reservation totals;
- Party CUSTOMER/SUPPLIER balances;
- cash/bank book balances;
- aging buckets;
- credit/risk exposure;
- Quote conversion progress;
- Order shipping/invoicing progress;
- Purchase receipt/invoice progress;
- Return physical/financial/refund progress;
- instrument remaining/maturity/custody portfolios;
- Warehouse task queues;
- reconciliation remaining;
- dashboards/KPIs.

## 9. Projection constraints
Projection tables/materialized views/caches:
- can be rebuilt from authoritative records;
- may be transactionally maintained for performance later;
- never accept direct business posting as their own authority;
- never change Invoice state to paid/open;
- never become the sole proof of source quantity remaining.

Valkey may cache projections but cannot be authoritative.

## 10. Aging exception
Aging may apply FIFO reduction logic over due-dated role-balance segments for reporting only.
That derived matching is not persisted as authoritative settlement allocation.

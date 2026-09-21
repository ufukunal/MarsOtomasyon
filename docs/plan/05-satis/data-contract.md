# Sales Conceptual Data Contract

Status: FROZEN logical/domain planning only. No physical SQL schema, column type or index is defined here.

## 1. Ownership

Sales owns:
- Quote, Quote Revision, Quote Line;
- Sales Order, Sales Order Line and amendment/version history;
- Sales-owned source/target commercial links;
- Proforma.

Inventory/Warehouse owns:
- authoritative Reservation records;
- inventory ledger and physical STOCK truth;
- warehouse/location/lot/serial movement truth.

Finance owns:
- account ledger;
- cash/bank ledgers;
- customer balance;
- financial COGS posting;
- Collection event;
- financial credit/refund.

No Invoice↔Collection allocation entity is required by the frozen B001 model.

## 2. Core conceptual relations

Quote
- has revisions and lines;
- one exact revision line may convert to many Sales Order lines through quantity-bearing conversion links.

Quote conversion link
- source Quote revision/line;
- target Sales Order/version/line;
- converted quantity;
- cumulative quantity cannot exceed effective offered quantity.

Sales Order
- has immutable historical versions/amendments;
- current effective version determines active commercial demand;
- downstream records preserve exact effective line/version source.

Sales Order amendment
- references prior effective order version;
- stores proposed/activated delta;
- reason, actor/time, approval evidence;
- never deletes processed history.

Reservation reference
- Sales Order effective line/version → Inventory Reservation record(s);
- reserved totals are derived, not independent Sales authority.

Dispatch
- source Sales Order effective line/version;
- physical inventory-ledger movement reference;
- reversal relation.

Sales Invoice
- source may be Dispatch, Sales Order or direct/source-less;
- direct/source-less Invoice has no inventory movement relation;
- all modes have account-ledger posting and financial COGS relation at POST;
- posted commercial/tax/FX calculations are immutable snapshot.

Collection
- Finance-owned balance event linked to customer context only;
- no authoritative per-invoice allocation/open item.

## 3. Authoritative vs derived

Authoritative:
- Quote revision content/history.
- conversion link quantities.
- Sales Order base/version/amendment history and effective ordered quantity.
- Inventory Reservation records.
- posted Dispatch quantities + inventory ledger.
- posted Invoice quantities/amounts + account ledger + COGS posting.
- Finance Collection and cash/bank/account ledgers.
- return physical/financial records in their owning modules.

Derived:
- Quote remaining conversion qty.
- order reserved/shipped/invoiced/returned totals.
- remaining-to-ship / remaining-to-invoice.
- customer current balance projection from account ledger.
- product current stock projection from inventory ledger.
- dashboards/reports.

Forbidden derived authority:
- invoice paid/open status;
- mutable customer.current_balance;
- mutable product.stock_quantity;
- copied Sales reservation total disconnected from Inventory.

## 4. Calculation and snapshot contract

Posted Invoice snapshot includes as applicable:
- customer legal/tax/address data;
- product/UOM identity;
- quantity;
- KDV-exclusive unit price;
- line discount;
- allocated document discount;
- taxable base;
- tax rate/treatment and line tax;
- transaction currency;
- currency minor-unit policy;
- FX source/type/date/rate;
- base-currency calculated values;
- source document identities/versions.

Rounding:
- decimal arithmetic only;
- posted amounts use currency minor unit;
- TRY = 2 decimals;
- midpoint away from zero;
- document totals sum rounded lines;
- discount-allocation residual uses deterministic largest-base/stable-line rule.

FX:
- default TCMB döviz alış by invoice/tax-event date;
- latest prior published business day when no rate exists for date;
- manual override preserves suggested and override metadata plus reason/actor/time.

Snapshots are historical denormalization and never become live master authority.

## 5. Controlled amendment data rules

- confirmed order history is append/version oriented;
- current effective order is derived from accepted base + active deltas;
- processed quantity cannot be erased by amendment;
- quantity decrease floor = max(net shipped, net invoiced);
- excess active Reservation must be released before reduced amendment activates;
- quantity increase creates new unprocessed eligible scope and does not auto-reserve;
- processed-line product/UOM identity is immutable;
- changed commercial terms for future scope use a new amendment line when processed scope exists;
- cancelled remainder is represented explicitly, not hard delete.

## 6. Approval data rules

Approval binds exact document revision/amendment snapshot.

Store conceptually:
- approval-required reason(s);
- policy source/version;
- submitter;
- approver;
- timestamps;
- result.

SoD invariant:
creator != approver.

A material document change after approval invalidates/requires re-evaluation of prior approval.

## 7. Concurrency and uniqueness risks for P3

Future durable DB strategy must protect:
- repeated Quote conversion over source remaining;
- concurrent Reservation;
- concurrent Dispatch against remaining;
- concurrent Invoice against eligible source;
- duplicate post/reverse;
- amendment activation against stale order version;
- Reservation release racing with Dispatch;
- duplicate external order ingest.

Exact PK/FK/unique/index/locking strategy belongs P3.

## 8. Scope

Company:
- mandatory transactional authority boundary.

Branch:
- only where numbering/ownership/accounting policy requires; P3 decides placement.

Warehouse:
- Reservation/Dispatch physical scope.
- Quote/Invoice do not gain warehouse authority merely from UI context.

Cross-company source-target links are forbidden.

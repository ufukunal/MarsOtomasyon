# Purchasing Conceptual Data Contract

Status: FROZEN logical/domain planning only. No SQL schema/index/type is defined here.

## 1. Authority model

Purchasing authoritative:
- Purchase Order and lines;
- PO commercial snapshot/version;
- Purchase source-target quantity links;
- Goods Receipt document identity/source links;
- Supplier Invoice commercial/match document identity;
- match exception/approval evidence;
- Purchase Return linkage.

Parties authoritative:
- Supplier Party identity/role.

Products authoritative:
- Product/Variant/UOM/barcode live master.

Inventory/Warehouse authoritative:
- physical Inventory Ledger;
- Warehouse/Location;
- lot/serial physical movement;
- disposition/status.

Finance authoritative:
- supplier account ledger/payable balance;
- Payment;
- settlement/allocation;
- valuation/costing;
- financial return adjustment/reversal.

## 2. Conceptual entities

### Purchase Order

Company-scoped commercial commitment.

Contains conceptually:
- supplier Party reference;
- status/version;
- currency/payment/delivery terms;
- lines;
- tolerance/policy snapshot where used;
- approval evidence;
- audit.

### Purchase Order Line

References:
- Product/Variant;
- entered UOM and accepted base conversion;
- ordered quantity;
- unit price/discount/tax snapshot;
- delivery/warehouse intent;
- cancelled remainder.

Derived progress:
- received;
- invoiced;
- remaining-to-receive;
- remaining-to-invoice.

### Goods Receipt

Physical/acceptance document.

For stockable lines references:
- PO line;
- Product/Variant/UOM;
- Warehouse/Location;
- entered/base quantity;
- lot/serial/expiry;
- initial disposition;
- Inventory Ledger movement.

For service/non-stock:
- may represent acceptance evidence without physical ledger movement.

### Receipt Source Link

PO line → Goods Receipt line.

Stores exact processed:
- quantity;
- UOM;
- base quantity;
- source version.

Cumulative processing is constrained by effective PO remainder + accepted tolerance.

### Supplier Invoice

Financial document.

Source modes:
- RECEIPT_3WAY
- PO_2WAY
- DIRECT_FINANCIAL_ONLY

Contains:
- supplier snapshot;
- product/service line snapshot;
- source links;
- commercial/tax/FX snapshot;
- match state;
- approval evidence;
- account-ledger posting reference after POST.

Supplier Invoice never owns physical stock movement.

### Invoice Source Link

For stockable:
Goods Receipt line → Supplier Invoice line, with PO lineage retained.

For service/non-stock:
PO line → Supplier Invoice line.

Stores exact quantity/value basis used for matching.

### Purchase Match

Conceptual comparison context:
- match type;
- PO;
- Receipt where applicable;
- Invoice;
- line results;
- quantity variance;
- price variance;
- configured tolerance/policy;
- status;
- approver/reason when exception accepted.

Match result is business evidence, not a second quantity/payable ledger.

### Purchase Return

Links:
- source Goods Receipt / physical lines;
- optional source Supplier Invoice;
- physical return effect;
- financial adjustment effect.

Physical and financial completion are separate.

## 3. Snapshot contract

PO accepted/sent snapshot preserves as applicable:
- Supplier legal/display identity;
- supplier address/reference;
- Product/Variant code/name;
- UOM/conversion;
- ordered quantity;
- price/discount/tax;
- currency/payment/delivery terms;
- tolerance/policy evidence.

Goods Receipt preserves:
- PO/source line/version;
- Product/Variant/UOM conversion;
- Warehouse/Location;
- lot/serial/expiry;
- received quantity;
- initial disposition.

Supplier Invoice POST preserves:
- Supplier legal/tax/address;
- Product/service/UOM;
- PO/Receipt source links;
- quantity;
- price/discount/tax;
- currency/FX source/date/rate;
- match/exception evidence.

Live master edits do not rewrite snapshots.

## 4. Quantity semantics

Per effective PO line:

`ordered_qty` — accepted PO quantity.

`received_qty` — net POSTED Goods Receipt quantity after receipt reversal.

`invoiced_qty` — net POSTED Supplier Invoice source quantity after financial reversal/correction.

`returned_qty_physical` — net physical Purchase Return quantity.

`remaining_to_receive` — effective ordered less net received less cancelled remainder, considering accepted over-receipt policy only for the current receipt validation.

For stockable:
`remaining_to_invoice = net eligible receipt qty - net invoiced qty`.

For service/non-stock:
remaining invoice basis follows PO quantity/value basis.

No generic persisted "remaining" overrides authoritative source links.

## 5. Tolerance policy

Company-scoped Purchasing Policy / Match Policy conceptually owns:
- over-receipt maximum;
- over-invoice maximum;
- price variance maximum;
- approval requirement;
- applicable supplier/category/product/company scope;
- effective version.

Defaults when absent:
- over-receipt = 0;
- over-invoice = 0;
- price variance = 0.

Any non-zero accepted exception requires approval.
Beyond configured maximum is BLOCKED.

Exact physical schema and precedence belong P3, but a single deterministic applicable policy must be selected at command time.

## 6. Status / quality boundary

Stockable Goods Receipt POST:
- physical quantity enters Inventory disposition QUARANTINE.

Quality:
- owns inspection result/evidence where required.

Inventory/Quality disposition action:
- QUARANTINE → AVAILABLE / QUALITY_HOLD / REWORK / DAMAGED / return path.

Purchasing stores/reference status/progress; it does not duplicate physical balance truth.

## 7. Financial boundary

Goods Receipt:
- no account payable.

Supplier Invoice:
- account payable increase via Finance ledger posting.

Payment:
- Finance-owned payable decrease + CASH/BANK OUT.

Purchasing must not persist authoritative:
- supplier.current_balance;
- invoice paid/open balance;
- payment allocation.

## 8. Calculation contract

Supplier Invoice reuses project central calculation convention:
- KDV-exclusive;
- line discount → document discount allocation → taxable base;
- line tax;
- currency minor-unit rounding;
- midpoint away-from-zero;
- document totals = sum of rounded lines.

FX:
- default TCMB döviz alış;
- invoice/tax-event date;
- prior published business day fallback;
- manual override audited/approved.

Posted snapshot immutable.

## 9. Return / reversal lineage

Required:
- original PO line → receipt line(s);
- PO/receipt → invoice line(s);
- Goods Receipt → physical Purchase Return;
- Supplier Invoice → financial adjustment;
- posted receipt/invoice → reversal.

Original records remain.

## 10. Concurrency risks for P3

Protect:
- duplicate Goods Receipt POST;
- concurrent receipt over same PO remainder;
- duplicate Supplier Invoice identity/source posting;
- same receipt quantity invoiced concurrently;
- stale match/tolerance approval;
- return beyond eligible received quantity;
- receipt reversal after downstream movement/invoice;
- cross-company source links.

Exact unique/locking/version strategy belongs P3.

## 11. Forbidden models

- Purchase Order creating stock/payable.
- Goods Receipt creating supplier payable.
- Supplier Invoice creating stock.
- mutable supplier balance on Purchasing master/document.
- copied stock quantity as Purchasing authority.
- match result as replacement for source documents/ledgers.
- silent posted document mutation.
- comma-separated source line IDs.

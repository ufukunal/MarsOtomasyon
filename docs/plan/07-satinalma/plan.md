# Purchasing Domain Plan

Status: COMPLETED / FROZEN — PLAN-005

## 1. Business objective

Create one traceable procurement chain where commercial commitment, physical receipt, supplier payable, match exceptions, returns and payment are distinct effects and cannot double-post each other.

## 2. Source classification

SOURCE:
- repo governance/Foundation/DB rules;
- frozen Sales/Party/Product/Inventory contracts;
- ERP/Finance/Warehouse skill contracts;
- V38 repository HTML Purchasing screens and notes.

OWNER-AUTHORIZED INFERENCE:
- current owner explicitly instructed this planning session to use repo/V38 as the guide and make practical inferences without unnecessary external research.
- the decisions below are frozen project policy unless the owner later changes them.

External internet research was not required for PLAN-005.

## 3. Scope

In:
- Purchase Order
- Goods Receipt
- Service/non-stock acceptance boundary
- Supplier Invoice
- 2-way / 3-way match
- quantity/price variance and tolerance ownership
- partial receipt/invoice
- Purchase Return linkage
- supplier Payment boundary
- tax/discount/rounding/FX planning policy
- permissions/SoD
- integrations/reporting
- Full Test Day backlog

Out:
- SQL/schema/migration
- application/API/UI implementation
- full Warehouse operations
- Finance settlement engine
- valuation/cost-layer method
- supplier portal/provider implementation

## 4. Frozen decisions

### PUR-D001 — Purchase Order effects

Purchase Order is commercial commitment only.

At DRAFT/SENT/APPROVED:
- DOC = yes
- RES = NONE
- STOCK = NONE
- ACCOUNT = NONE
- CASH/BANK = NONE
- COST = NONE

PO never creates supplier payable or physical stock.

### PUR-D002 — Supplier and Product eligibility

New PO requires:
- ACTIVE Party with ACTIVE SUPPLIER role in same company;
- ACTIVE PURCHASABLE Product/Variant/UOM;
- company scope match.

Snapshots preserve supplier legal/tax/address and Product/Variant/UOM/commercial values accepted by the document.

### PUR-D003 — Purchase Order states

States:
- DRAFT
- PENDING_APPROVAL
- APPROVED
- SENT
- ON_HOLD
- CLOSED
- CANCELLED_REMAINDER
- CANCELLED

Approval is conditional:
- policy-compliant PO may move DRAFT → APPROVED/SENT without manual approval;
- exception PO enters PENDING_APPROVAL.

Approval triggers:
- price/payment/currency terms outside company Purchasing Policy;
- non-zero over-receipt or over-invoice tolerance request;
- direct/source-less Supplier Invoice exception linked to Purchasing;
- manual FX override;
- material post-approval commercial amendment.

SoD:
creator != approver when approval is required.

Exact monetary thresholds are Purchasing Policy configuration, not hard-coded plan values.

### PUR-D004 — PO amendment / remainder

Sent/approved PO history is not silently rewritten.

Allowed controlled changes affect unprocessed remainder:
- decrease/cancel remaining quantity;
- increase quantity subject to policy/approval;
- delivery date/warehouse/contact;
- future commercial terms subject to reapproval.

Processed received/invoiced history remains immutable.

### PUR-D005 — Goods Receipt physical recognition

Goods Receipt POST:
- DOC = POSTED
- STOCK IN = accepted STOCKABLE quantity
- ACCOUNT payable = NONE
- CASH/BANK = NONE

For STOCKABLE lines:
- physical quantity enters QUARANTINE at POST;
- Warehouse/Location, UOM/base quantity and required lot/serial/expiry are captured;
- AVAILABLE requires explicit release/disposition after QC or accepted no-inspection disposition.

For SERVICE/non-stock lines:
- Goods Receipt may be used as operational acceptance evidence;
- STOCK = NONE.

### PUR-D006 — Receipt quantity / tolerance

Partial and short receipt:
- allowed;
- remaining_to_receive stays open until later receipt, cancel remainder or close.

Over-receipt:
- default hard BLOCK at ordered remaining quantity.
- company Purchasing Policy may define a positive line/category/supplier tolerance.
- any quantity above ordered quantity requires explicit match/tolerance exception approval even when within configured maximum.
- quantity beyond configured maximum is blocked.

Deterministic cap:
`cumulative_received <= ordered_qty + allowed_over_receipt_qty`

When no policy exists:
`allowed_over_receipt_qty = 0`.

### PUR-D007 — Supplier Invoice stock/payable behavior

Supplier Invoice POST:
- ACCOUNT payable increases;
- STOCK = NONE in every source mode;
- CASH/BANK = NONE;
- tax/FX/calculation snapshot becomes immutable.

A Supplier Invoice never performs Goods Receipt stock-in.

### PUR-D008 — Supplier Invoice source modes

1. STOCKABLE GOODS:
   - posted Goods Receipt source is mandatory;
   - invoice quantity must link to one/more posted receipt lines;
   - 3-way match PO ↔ Receipt ↔ Invoice required before normal POST.

2. SERVICE / NON-STOCK:
   - PO-sourced invoice uses 2-way PO ↔ Invoice match;
   - optional service/non-stock acceptance evidence may be linked;
   - no physical stock effect.

3. DIRECT / SOURCE-LESS:
   - allowed only for SERVICE/NON-STOCK financial-only purchase;
   - requires explicit permission, reason and conditional approval;
   - STOCK = NONE;
   - cannot be used to acquire stockable goods without receipt.

### PUR-D009 — Invoice quantity tolerance

For STOCKABLE goods:
- normal invoiceable quantity cannot exceed net eligible posted receipt quantity not already invoiced.
- over-invoice default BLOCK.
- Purchasing Policy may define an explicit maximum exception tolerance.
- any invoiced quantity above eligible receipt requires exception approval;
- beyond configured maximum is blocked.

For SERVICE/NON-STOCK PO match:
- invoiced quantity/value cannot exceed eligible PO remainder unless explicit policy + approval permits exception.

No policy = zero over-invoice tolerance.

### PUR-D010 — 2-way / 3-way Match

3-way match applies to STOCKABLE goods:
- Purchase Order
- Goods Receipt
- Supplier Invoice

2-way match applies to PO-sourced SERVICE/NON-STOCK:
- Purchase Order
- Supplier Invoice

Direct financial-only invoice:
- match type DIRECT_EXCEPTION;
- requires reason/permission/approval.

Match dimensions:
- source identity/company/supplier;
- Product/Variant/UOM;
- quantity;
- unit price;
- line/document discount;
- tax treatment;
- currency.

Match statuses:
- PENDING
- MATCHED
- EXCEPTION
- APPROVED_EXCEPTION
- BLOCKED
- NOT_REQUIRED

Invoice POST rule:
- MATCHED or APPROVED_EXCEPTION for source-matched lines;
- DIRECT_EXCEPTION approval for source-less lines.

### PUR-D011 — Price variance

Normal PO-sourced invoice compares Invoice commercial price to accepted PO commercial snapshot.

Default price variance tolerance:
- zero unless company Purchasing Match Policy explicitly defines otherwise.

Within zero/default:
- exact match required.

When policy defines tolerance:
- variance within allowed band is recorded visibly;
- any non-zero variance requires match exception approval before POST;
- variance beyond configured maximum is BLOCKED.

No silent price overwrite of PO or Receipt.

### PUR-D012 — Receipt disposition

V38-guided default:
- STOCKABLE Goods Receipt POST → QUARANTINE.
- release to AVAILABLE is a separate Inventory/Quality disposition effect.

If no inspection is required by active Quality/Purchasing policy:
- release may be performed immediately after POST as a distinct auditable disposition action;
- receipt movement itself remains QUARANTINE-first.

Rejected/damaged quantity remains in an appropriate non-available disposition until return/disposal/rework workflow.

### PUR-D013 — Supplier Invoice calculation policy

Purchasing uses the same central deterministic monetary mechanics frozen for Sales unless a later Finance decision supersedes them:

- KDV-exclusive line pricing;
- line discount → allocated document discount → taxable base → KDV;
- decimal arithmetic only;
- posted monetary amounts use currency minor unit;
- midpoint rounding away-from-zero;
- line tax rounded at line level;
- document totals are sums of rounded line amounts;
- no hidden header balancing.

Posted Supplier Invoice snapshot is immutable.

### PUR-D014 — FX

Default Supplier Invoice FX policy:
- TCMB döviz alış;
- rate date = Supplier Invoice / tax-event document date;
- if no published rate for that date, latest prior published business-day rate;
- controlled manual override requires permission, reason, audit and approval.

Posted invoice stores immutable:
- currency
- source/type
- source date
- rate
- base-currency calculated values.

This mirrors the already-frozen Mars Sales FX convention for one project-wide finance rule.

### PUR-D015 — Payment boundary

Payment is Finance-owned.

Payment effect:
- supplier liability decreases;
- CASH/BANK decreases;
- STOCK = NONE.

Purchasing may expose an action/link to Finance Payment, but:
- settlement/allocation model belongs PLAN-007;
- Purchasing does not maintain authoritative paid/open balance fields.

### PUR-D016 — Purchase Return

Physical supplier return and financial correction are separate linked concerns.

Physical Return:
- STOCK OUT from Inventory/Warehouse;
- source Goods Receipt/lot/serial traceability preserved.

Financial adjustment:
- reduces/reverses supplier payable as applicable;
- no physical stock effect.

A return may need one or both sides depending source/history; completion status shows them separately.

### PUR-D017 — Reversal

Posted Goods Receipt:
- reversed by compensating physical movement;
- original receipt/ledger history remains.

Posted Supplier Invoice:
- reversed by Finance/account compensating entry;
- original invoice remains.

Dependent downstream links:
- reversal is blocked or requires compensating dependent flow when quantity has been transferred/consumed/returned/invoiced in a way that would make direct reversal inconsistent.

No silent delete/update of posted effect.

### PUR-D018 — Quantity semantics

Per PO line:
- ordered_qty
- received_qty = net posted receipt qty after reversal
- invoiced_qty = net posted supplier invoice source qty after reversal/correction
- returned_qty_physical = net purchase-return physical qty
- financially_adjusted_qty/value = financial adjustment authority
- remaining_to_receive
- remaining_to_invoice

No ambiguous generic remaining quantity is authoritative.

### PUR-D019 — Cost / valuation boundary

Goods Receipt may preserve PO commercial cost basis as valuation input/reference.

Purchasing does not freeze:
- FIFO
- weighted average
- standard cost
- landed-cost allocation
- inventory revaluation

Authoritative valuation method/cost layers belong Finance/Costing.

Supplier Invoice commercial variance is recorded for later valuation/accounting treatment, not silently applied by Product master.

## 5. Effect matrix

| Action | DOC | RES | STOCK | ACCOUNT | CASH/BANK | COST/VALUATION |
|---|---|---|---|---|---|---|
| PO Draft/Approve/Send | state | NONE | NONE | NONE | NONE | NONE |
| Goods Receipt POST stockable | POST | NONE | IN → QUARANTINE | NONE | NONE | valuation input only |
| Goods Receipt service/non-stock acceptance | POST | NONE | NONE | NONE | NONE | NONE |
| Receipt Release | linked disposition | NONE | QUARANTINE → AVAILABLE | NONE | NONE | valuation unchanged by status alone |
| Supplier Invoice POST | POST | NONE | NONE | payable increase | NONE | Finance-owned |
| Payment POST | Finance event | NONE | NONE | payable decrease | cash/bank OUT | NONE |
| Physical Purchase Return | return doc | NONE | STOCK OUT | NONE | NONE | Finance/Costing later |
| Financial Return Adjustment | finance correction | NONE | NONE | payable decrease/reverse | refund/payment separately | Finance-owned |

## 6. Reviewer outcome

ERP: effect points, partials, source-target and reversal are deterministic.
Finance: payable only at Supplier Invoice; Payment separate; calculation/FX/reversal deterministic.
Warehouse: Goods Receipt is only Purchasing physical STOCK IN; quarantine-first and traceability explicit.
Database: no second stock/payable truth; future constraints can protect source quantities/idempotency.
Architecture: Purchasing, Inventory, Quality and Finance boundaries are explicit.
Developer: no implementation depends on undefined physical schema.
Testing: over-receipt/invoice, match, duplicate post, partial, reversal and return invariants are testable.
UX: progress/match/exception states can be exposed without hiding source quantities.

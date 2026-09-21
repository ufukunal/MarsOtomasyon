# Sales Domain Plan

## 1. Business objective

Create one traceable commercial chain in which customer intent, ordered quantity, inventory commitment, physical shipment, financial receivable, collection and return effects are distinguishable and cannot double-post each other.

Expected business value:
- reduce duplicate entry between quote/order/dispatch/invoice;
- make partial fulfilment and remaining quantities visible;
- prevent stock from being reduced at both dispatch and invoice;
- prevent invoices/collections from silently mutating historical documents;
- preserve customer/product/price/tax/currency history;
- expose operational exceptions without replacing them with hidden automatic behavior.

## 2. Scope

In:
- Quote
- Sales Order
- Reservation linkage
- Dispatch
- Sales Invoice
- Collection linkage
- Sales Return linkage
- Proforma as auxiliary Sales document
- V38 Sales screen classification
- planning-level permissions/integration/report contracts

Out:
- physical SQL schema
- API/UI implementation
- full Finance settlement implementation
- full Returns/RMA workflow
- marketplace-specific order behavior
- provider-specific e-document/carrier implementation

## 3. Source precedence applied

Primary project rules come from current owner instructions, repository planning/governance, Sales/ERP/Finance/Warehouse skill contracts and V38 product evidence.

Where V38 and a generic skill conflict, this plan records a decision gate instead of silently selecting one.

## 4. Business ownership

### Quote
Process owner: Sales.
Purpose: record a customer-facing commercial proposal and its revisions without stock/accounting effect.
Value: preserves what was offered, when, at what price/terms and which revision became the order source.

### Sales Order
Process owner: Sales, with Warehouse and Finance consumers.
Purpose: represent accepted/confirmed customer demand and provide the source for reservation, dispatch and invoicing.
Value: central quantity-control point for fulfilment.

### Reservation
Process owner: Inventory/Warehouse; initiated from a Sales Order context.
Purpose: commit available inventory to an order without changing physical on-hand.
Value: prevents uncontrolled oversell while keeping physical movement separate.

### Dispatch
Process owner: Warehouse/Shipping.
Purpose: record and post physical goods leaving a warehouse against source order lines.
Value: authoritative physical fulfilment point and traceability source.

### Sales Invoice
Process owner: Finance/Accounting, created from Sales commercial context.
Purpose: financially recognize customer receivable.
Value: authoritative financial document and legal/commercial snapshot.

### Collection
Process owner: Finance/Treasury.
Purpose: reduce customer receivable and increase cash/bank through a separate financial event.
Value: keeps settlement/cash movement independent from invoice posting.

### Sales Return Link
Process owner: Returns/Quality for physical return; Finance for credit/refund.
Purpose: link a prior sale/dispatch/invoice to later physical and/or financial correction without rewriting original history.

### Proforma
Process owner: Sales.
Purpose: informational commercial document generated from Quote or Sales Order.
Effects: no authoritative RES/STOCK/ACCOUNT/CASH-BANK effect by itself.

## 5. Transaction boundaries at planning level

- Quote save/revision is a Sales document transaction only.
- Order confirm is a Sales document transaction; it is not stock-out or receivable posting.
- Reservation create/release is an Inventory commitment transaction linked to order line.
- Dispatch finalization/posting atomically records dispatch state and inventory-ledger physical effect; related reservation consumption/release belongs to the same business action.
- Invoice finalization/posting atomically records invoice state and account-ledger receivable effect.
- Collection posting is a separate Finance transaction.
- Return receipt/disposition and financial credit/refund are separate transactions linked by return/RMA context.
- External send operations (customer message, e-document provider, carrier/provider) occur through outbox/worker where reliability matters; external provider success is not the DB commit condition.

## 6. Locked invariants

- quote.RES = NONE; quote.STOCK = NONE; quote.ACCOUNT = NONE.
- order.STOCK = NONE; order.ACCOUNT = NONE.
- reservation.STOCK = NONE.
- posted dispatch creates the physical stock-out.
- dispatch-sourced invoice cannot create a second stock-out.
- posted invoice creates receivable.
- collection is separate from invoice posting.
- posted document/ledger history is immutable except by linked reversal/compensation.
- source/target links are line-level where quantity control depends on lines.
- partial shipment and partial invoicing are supported.
- downstream processed quantity cannot be lost by editing upstream history.
- authoritative balances/stocks are derived from ledgers, not mutable master-card balance/stock fields.

## 7. Decision gates — OWNER DECISION REQUIRED

### SALES-B001 — Collection allocation model
Conflict:
- V38 final balance-based UI explicitly says collections are not matched to invoices and open_items / settlement_workspace are removed from the active model.
- accounting-finance-specialist describes single/multi-document/advance/partial allocation as a general finance capability.

Owner must choose one Sales/Finance project policy:
A. balance-only current account: collection changes total customer balance and does not allocate to invoice;
B. allocation-capable ledger: collection may allocate to one/many invoices or remain advance/unallocated;
C. another explicit hybrid rule.

Until decided:
- invoice posting and account-balance effect are valid;
- collection remains a separate financial event;
- invoice paid/unpaid/open-balance semantics must not be implemented.

### SALES-B002 — Source-less/direct Sales Invoice stock behavior
V38 permits Yeni Satış Faturası UI, while domain rules state direct invoice stock effect needs an explicit rule.

Owner must decide:
A. direct invoice is financial-only; physical dispatch is always separate;
B. direct invoice may be a combined physical+financial posting under explicit conditions;
C. source-less direct invoice is forbidden.

Until decided, a direct/source-less invoice may be drafted but its stock-affecting post behavior is BLOCKED.

### SALES-B003 — Quote partial conversion
Line-level traceability is required, but sources do not establish whether one quote revision can be converted partially to one or multiple Sales Orders.

Owner must decide:
- full conversion only; or
- line/quantity partial conversion; and whether repeated conversion is allowed.

### SALES-B004 — Reservation trigger policy
The contract permits reservation only against eligible order lines and V38 exposes an explicit Rezervasyon Yap action. Generic ERP guidance also permits reservation after approval.

Owner must decide whether reservation is:
- explicitly user-triggered,
- automatically triggered on a specific order transition,
- or policy-configurable.

Effect semantics do not change: Reservation changes RES, never physical STOCK.

### SALES-B005 — Cost/COGS recognition point
Physical stock-out at Dispatch is locked, but the financial COST/COGS recognition point is not defined in repository sources.

Owner/Finance decision required before accounting implementation:
- at dispatch,
- at invoice,
- or another explicit accounting policy.

Inventory valuation effect and financial COGS must not be conflated silently.

### SALES-B006 — Sales tax/discount/rounding policy
Repository defines that invoice tax, currency, exchange rate and snapshots matter, but exact policies remain missing:
- tax-inclusive/exclusive calculation sequence,
- line vs document discount order,
- line/document tax rounding,
- decimal precision/minor-unit rule,
- exchange-rate source/date/type.

These must be decided before invoice calculation implementation.

### SALES-B007 — Approval policy
V38 contains internal approval actions/status concepts but does not define mandatory conditions or thresholds.

Owner must decide:
- whether Quote approval is mandatory and when;
- whether Sales Order approval is mandatory and when;
- any approval thresholds/SoD rules.

No amount/discount/risk threshold may be invented.

### SALES-B008 — Confirmed-order amendment policy
Sources require history and downstream consistency but do not define how confirmed order changes are performed after reservation/dispatch/invoice exists.

Owner must choose:
- controlled amendment/version,
- cancel remaining + new order,
- another explicit rule.

At minimum, already processed quantities can never be silently rewritten away.

## 8. Controls and exceptions

Required controls:
- prevent shipment above permitted source quantity;
- prevent invoicing above invoiceable source quantity;
- prevent duplicate posting/idempotent re-post;
- prevent dispatch-sourced invoice stock double-post;
- prevent terminal/cancelled lines from future processing;
- show stale/concurrency conflicts rather than silently overwrite;
- require server-side permissions and company/branch/warehouse scope checks;
- preserve source/target traceability through reversals.

Operational exceptions to support explicitly:
- insufficient available stock;
- partial reservation;
- partial dispatch;
- partial invoice;
- cancelled remaining quantity;
- dispatch reversal;
- invoice reversal;
- return after invoice;
- return after collection;
- inactive master referenced by historical document;
- e-document/provider failure after local invoice commit.

## 9. Future KPI candidates

These are future Reporting contracts, not finalized formulas in PLAN-002:
- quote conversion;
- order cycle time;
- fill rate;
- OTIF;
- sales return rate;
- sales gross margin;
- order backlog;
- dispatch delay.

Formula/grain/filter/currency/status rules remain for Reporting planning.

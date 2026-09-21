# Sales Domain Plan

Status: COMPLETED / FROZEN — PLAN-002

## 1. Business objective

Create one traceable commercial chain in which customer intent, ordered quantity, inventory commitment, physical shipment, financial receivable, COGS, collection and return effects are distinguishable and cannot double-post each other.

## 2. Scope

In:
- Quote and revisions
- Sales Order
- Reservation linkage
- Dispatch
- Sales Invoice
- Collection linkage
- Sales Return linkage
- Proforma
- planning-level permissions, integrations and reports

Out:
- physical SQL schema/migrations
- application/API/UI implementation
- provider-specific integrations
- full Finance settlement engine
- full Returns/RMA workflow

## 3. Ownership and transaction boundaries

- Quote save/revision: Sales DOC only.
- Sales Order confirm: Sales DOC only; no STOCK or ACCOUNT posting.
- Reservation create/release: Inventory commitment linked to Sales Order line; non-physical.
- Dispatch POST: atomic Dispatch state + physical inventory-ledger STOCK OUT + related Reservation consumption/release.
- Sales Invoice POST: atomic Invoice state + ACCOUNT receivable + financial COGS recognition.
- Collection POST: separate Finance transaction reducing customer balance and increasing CASH/BANK.
- Return physical receipt/disposition and financial credit/refund are separate linked transactions.
- External sends use outbox/worker where reliability matters; provider success is not DB commit condition.

## 4. Frozen owner decisions

### SALES-B001 — DECIDED — Balance-only collection

- Collection reduces total customer current-account balance.
- No Collection → Invoice allocation relationship is authoritative.
- Invoice-level paid/unpaid/open balance is not authoritative and must not be presented as accounting truth.
- No open-item/settlement workspace is part of the active Sales model.
- Collection remains a separate Finance event.

Impact:
- Sales Invoice UI/reporting may show customer account context, but not per-invoice settlement status.
- Account ledger remains authoritative.

### SALES-B002 — DECIDED — Direct Invoice financial-only

- Direct/source-less Sales Invoice may POST receivable.
- It never creates physical STOCK OUT.
- Physical goods movement requires a separate Dispatch.
- Dispatch-sourced and direct invoices cannot duplicate Dispatch inventory movement.

Impact:
- Direct invoice POST is a Finance/Sales financial action only.
- Stock ownership stays Inventory/Warehouse.

### SALES-B003 — DECIDED — Partial/repeated Quote conversion

- Conversion is line/quantity based.
- One Quote revision may create multiple Sales Orders.
- Every conversion stores exact source revision, source line and converted quantity.
- Cumulative converted quantity cannot exceed effective offered quantity.
- Quote becomes CONVERTED when every line's remaining conversion quantity is zero.
- Before that point, partial conversion progress remains visible.

### SALES-B004 — DECIDED — Manual Reservation

- Reservation starts only through an explicit authorized user action on an eligible Sales Order.
- Order confirmation does not auto-reserve.
- Reservation affects RES only, never physical STOCK.
- Increase/decrease/release uses authoritative Inventory reservation records.

### SALES-B005 — DECIDED — COGS at Invoice

- Physical STOCK OUT remains Dispatch POST.
- Financial COGS recognition occurs at Sales Invoice POST.
- Inventory movement and financial COGS are separate effects.
- Invoice reversal reverses the financial COGS effect without rewriting Dispatch history.

### SALES-B006 — DECIDED — Sales calculation policy

#### A. Price/tax mode
- Commercial/unit prices are KDV-exclusive.
- KDV is calculated on the net taxable base after accepted discounts.

#### B. Discount/tax sequence
1. line discount;
2. document discount allocation;
3. taxable base;
4. KDV.

Document discount is allocated proportionally over eligible line bases so tax is deterministically attributable per line.

#### C. Rounding
- All calculations use decimal arithmetic; binary float/double is forbidden.
- Currency minor-unit precision is authoritative for posted monetary amounts; TRY uses 2 decimals.
- Unit price, percentage and FX calculations may retain higher internal decimal precision and are not rounded to minor unit before extension.
- Midpoint rounding rule is commercial half-up / away-from-zero.
- Line taxable base is rounded to currency minor unit after discounts.
- KDV is calculated and rounded per line to currency minor unit.
- Document net, tax and gross totals are sums of rounded line amounts; there is no independent hidden header recalculation.
- Proportional document-discount allocation residual is assigned deterministically to the eligible line with the largest post-line-discount base; ties use stable line sequence.
- Hidden balancing is forbidden. If a future legal/provider requirement needs a document-level rounding adjustment, it must be explicit, auditable and separately classified; it may not silently mutate line/tax history.

#### D. FX
- Default authoritative reference for Sales Invoice base-currency conversion is TCMB published döviz alış rate.
- Rate date is the invoice/tax-event document date.
- If TCMB publishes no rate for that date, use the latest prior published business-day rate.
- If the currency is not directly published, use an auditable cross-rate derived from the authoritative source.
- Manual override is allowed only with explicit permission `sales.invoice.fx_override`, mandatory reason, and audit of suggested/source rate, source date, overridden rate, actor and timestamp.
- Any manual FX override is a B007 approval exception.
- Posted Invoice stores immutable currency/rate/source/date metadata and calculated base-currency snapshot.

This is a Mars project policy. TCMB itself states its indicative rates are not binding between private parties; Mars selects this source for deterministic default behavior.

#### E. Posted snapshot
- Posted Invoice calculation inputs/results are immutable historical snapshot.
- Later master/rate changes do not mutate posted tax, discount, currency, FX or monetary values.
- Correction uses reversal/correction workflow.

### SALES-B007 — DECIDED — Conditional approval + SoD

Approval is exception-based rather than mandatory for every standard document.

Authoritative policy owner/source:
- company-scoped Sales Commercial Policy under Settings/configuration;
- numerical tolerances are configuration, not hard-coded Sales code;
- when the applicable policy is missing, manual commercial deviations require approval (fail closed).

Quote requires approval when any condition is true:
- unit price is manually overridden outside active commercial policy;
- line/document discount is manually overridden outside active policy tolerance;
- payment terms are outside active policy;
- manual FX override is used.

Sales Order requires approval when any condition is true:
- commercial price/discount/currency/payment terms deviate from the accepted Quote;
- a direct/source-less order contains a commercial-policy exception;
- manual FX override is used;
- a post-confirmation controlled delta increases commercial exposure or changes commercial terms.

Standard documents within active policy and unchanged Orders created from an accepted Quote do not require mandatory approval.

SoD:
- creator != approver for every approval-required document/amendment;
- approval is server-side and binds the exact document revision/amendment snapshot;
- a material change after approval invalidates that approval and requires re-evaluation/reapproval.

### SALES-B008 — DECIDED — Controlled delta amendment

Confirmed Sales Orders are not silently edited. An amendment creates an audited delta/version against the current effective order.

Allowed amendment operations:
- add a new line;
- increase ordered quantity;
- decrease/cancel only unprocessed remainder;
- change requested delivery date, shipping/contact instructions and notes;
- change commercial terms only for future unprocessed scope and subject to B007 approval.

Rules:
- product/UOM identity of an already processed line is immutable; a different product/UOM uses cancelled eligible remainder + new amendment line.
- ordered quantity may never be reduced below max(net shipped quantity, net invoiced quantity).
- active Reservation above the amended eligible remainder must be released before amendment activation; release failure/concurrency conflict blocks activation.
- quantity increase creates new eligible remainder but does not auto-reserve because B004 is manual.
- posted Dispatch and posted Invoice records are immutable.
- if a line already has posted Dispatch or Invoice, price/discount/tax/currency changes do not rewrite that processed scope; changed commercial terms use a new amendment line for future scope.
- amendment lifecycle is DRAFT → validation → required reservation release/reallocation → conditional approval if B007 triggers → ACTIVE.
- every amendment records sequence/version, reason, actor/time, before/after delta and approval evidence where required.
- downstream source-target links reference the effective order line/version used.
- ordered/reserved/shipped/invoiced/remaining projections are recalculated from effective order authority plus linked authoritative downstream records; processed totals are never manually overwritten.
- cancelled remainder is a negative controlled delta, not history deletion.

## 5. Locked invariants

- quote.RES/STOCK/ACCOUNT/CASH-BANK = NONE.
- order.STOCK/ACCOUNT = NONE.
- reservation.STOCK = NONE.
- Dispatch POST is normal physical STOCK OUT.
- Sales Invoice POST creates receivable and COGS.
- Dispatch-sourced/direct Invoice does not create STOCK OUT.
- Collection is separate and balance-only.
- posted history uses reversal/compensation.
- partial/repeated conversion, shipment and invoice use line-level source-target traceability.
- PostgreSQL and ledgers remain authoritative.

## 6. Controls

- prevent shipment above effective ordered quantity;
- prevent invoicing above eligible source quantity;
- prevent duplicate posting/idempotency failures;
- prevent stock double-post;
- prevent processing cancelled remainder;
- enforce approval/SoD server-side;
- surface stale/concurrency conflicts;
- preserve source/target and reversal history.

## 7. Reviewer outcome

ERP: effects and quantity/source-target semantics are deterministic.
Accounting: receivable, balance-only collection, Invoice COGS, tax/rounding/FX and reversal are deterministic.
Warehouse: Reservation is non-physical; stock-out remains Dispatch only.
Database: no second authoritative stock/balance/settlement truth is introduced.
Architecture: Sales/Inventory/Finance boundaries and transaction ownership remain explicit.
Testing: owner decisions map to testable invariants.
UX: state/source/original/processed/remaining and approval/amendment state can be shown without false accounting status.

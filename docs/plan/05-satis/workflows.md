# Sales Workflows

Status: FROZEN — PLAN-002

## 1. End-to-end chain

Quote
→ partial/repeated line conversion
→ Sales Order
→ manual Reservation
→ Dispatch
→ Sales Invoice
→ balance-only Collection context
→ Sales Return linkage

Direct Sales Invoice is allowed as financial-only; it is not a physical shipment path.

## 2. Effect matrix

| Object / Action | DOC | RES | STOCK | ACCOUNT | CASH/BANK | COST/COGS |
|---|---|---|---|---|---|---|
| Quote draft/revision/accept | create/state/revision | NONE | NONE | NONE | NONE | NONE |
| Quote → Order partial conversion | source-target links | NONE | NONE | NONE | NONE | NONE |
| Sales Order confirm | state | NONE | NONE | NONE | NONE | NONE |
| Sales Order controlled delta activate | version/delta | release/reallocate only when needed | NONE | NONE | NONE | NONE |
| Reservation create | link | INCREASE | NONE | NONE | NONE | NONE |
| Reservation release | link | DECREASE | NONE | NONE | NONE | NONE |
| Dispatch POST | POST | consume/release | STOCK OUT | NONE | NONE | NONE |
| Dispatch reverse | reversal link | restore/re-evaluate | reverse physical effect | NONE | NONE | NONE |
| Invoice POST from Dispatch/Order | POST | NONE | NONE | receivable increase | NONE | COGS recognize |
| Direct Invoice POST | POST | NONE | NONE | receivable increase | NONE | COGS recognize where invoiced goods/cost basis is eligible |
| Invoice reverse | reversal link | NONE | NONE | receivable reverse | NONE | COGS reverse |
| Collection POST | Finance event | NONE | NONE | customer balance decrease | cash/bank increase | NONE |
| Collection allocation | NOT USED | NONE | NONE | NONE | NONE | NONE |
| Physical return | linked return | NONE | STOCK IN/non-available disposition | NONE | NONE | return valuation policy later |
| Financial credit/refund | linked finance correction | NONE | NONE | receivable decrease | refund separately | reverse/adjust per Finance/Returns |
| Proforma | informational | NONE | NONE | NONE | NONE | NONE |

Audit is required for state-changing/post/reverse/approval/amendment actions. Outbox is used for reliable external effects, never as business truth.

## 3. Quote lifecycle and conversion

States:
DRAFT → optional PENDING_INTERNAL_APPROVAL → CUSTOMER_REVIEW → ACCEPTED → PARTIALLY_CONVERTED → CONVERTED.

Other terminal/history states:
SUPERSEDED, EXPIRED, CANCELLED.

Rules:
- external/customer-reviewed history is never overwritten; revision creates a new revision.
- conversion points to exact Quote revision + line + converted quantity.
- repeated conversion is allowed.
- cumulative converted quantity <= effective offered quantity.
- remaining_conversion_qty is derived per line.
- if any line has remaining > 0 after conversion, state/progress is PARTIALLY_CONVERTED.
- when all line remaining quantities are 0, state becomes CONVERTED.
- Quote has no reservation, stock, account or cash effect.

Approval:
- standard Quote within Sales Commercial Policy does not require mandatory approval.
- commercial-policy exception or manual FX override requires approval.
- creator cannot approve own Quote.

## 4. Sales Order lifecycle

States:
DRAFT → optional PENDING_APPROVAL → CONFIRMED → ON_HOLD / PARTIALLY_COMPLETED / COMPLETED / CANCELLED_REMAINDER.

Pre-processing cancellation may reach CANCELLED.

Rules:
- CONFIRMED is commercial demand only.
- Reservation is manual and separate.
- shipping-complete and invoicing-complete remain separate derived concepts.
- COMPLETED requires no further eligible operational/financial remainder according to linked quantities; UI must not hide which dimension is complete.

### Controlled delta amendment

A confirmed order is amended by versioned delta, not in-place history rewrite.

Amendment lifecycle:
AMENDMENT_DRAFT
→ validate against effective quantities/current version
→ release/reallocate excess Reservation if needed
→ PENDING_APPROVAL when B007 triggers
→ ACTIVE.

Allowed:
- add line;
- quantity increase;
- decrease/cancel unprocessed remainder;
- delivery/contact/notes changes;
- future-scope commercial term changes with approval.

Forbidden:
- quantity below max(net shipped, net invoiced);
- changing historical shipped/invoiced quantities;
- changing product/UOM on processed line;
- mutating posted downstream documents.

Reservation handling:
- quantity decrease first requires release of reservation above new eligible remainder.
- if release cannot be completed due stale/concurrent state, amendment fails with conflict.
- quantity increase does not auto-reserve.

Commercial term changes:
- if processed Dispatch/Invoice exists, existing processed scope keeps original terms/snapshots.
- future different terms are represented with eligible remainder cancellation/new amendment line.
- change after prior approval re-evaluates B007 and may require reapproval.

Each ACTIVE amendment increments effective version and records reason, actor/time, before/after delta and approval evidence.

## 5. Reservation lifecycle

Reservation is Inventory-owned, non-physical commitment.

REQUESTED/CREATED → ACTIVE → PARTIALLY_CONSUMED → CONSUMED
or RELEASED/CANCELLED.

- only explicit authorized user action initiates Reservation;
- Order confirm never auto-reserves;
- Dispatch consumes/releases applicable reservation;
- oversell/concurrency protection belongs authoritative Inventory implementation.

## 6. Dispatch lifecycle

DRAFT → PICKING → READY → POSTED → HANDED_OVER → DELIVERED.

Pre-post cancellation: CANCELLED.
Posted correction: REVERSED by linked compensating physical effect.

Physical recognition:
- only POSTED creates STOCK OUT.
- picking/packing/handoff never posts a second movement.
- one order line may feed multiple dispatches.

## 7. Sales Invoice lifecycle

DRAFT → POSTED → REVERSED.
Draft may become CANCELLED before POST.

At POST:
- ACCOUNT receivable increases.
- financial COGS is recognized.
- STOCK = NONE in every Invoice source mode.

Source modes:
- Dispatch-sourced: invoiceable from eligible posted Dispatch lines.
- Order-sourced: invoiceable from eligible Order quantity without physical stock effect.
- Direct/source-less: financial-only and never physical stock-out.

Provider/e-document send state is separate from local financial POSTED state.

## 8. Calculation workflow

Tax mode:
KDV-exclusive.

Sequence per line:
1. quantity × unit price using high decimal precision;
2. line discount;
3. allocated document discount;
4. taxable base rounded to currency minor unit;
5. KDV calculated and rounded per line;
6. gross line amount.

Document discount allocation:
- proportional to eligible post-line-discount bases;
- calculate at high precision;
- residual after minor-unit rounding goes to eligible line with largest base, stable line sequence tie-break.

Rounding:
- decimal only;
- TRY posted money: 2 decimals;
- other currencies: configured/ISO minor unit;
- midpoint: away from zero;
- document net/tax/gross = sums of rounded lines;
- no hidden balancing.

FX:
- default TCMB döviz alış.
- rate date = invoice/tax-event document date.
- absent-date rate → latest prior published business-day rate.
- unsupported direct currency → auditable cross-rate from authoritative source.
- manual override requires `sales.invoice.fx_override` + reason + B007 approval.
- posted rate/source/date and calculated results are immutable snapshot.

## 9. Collection

Collection is Finance-owned and balance-only.

- customer current-account balance decreases;
- cash/bank increases;
- no invoice allocation;
- no invoice paid/unpaid/open-balance authority;
- collection remains separate from Invoice POST.

## 10. Quantity semantics

- ordered_qty: effective authoritative order line quantity after active amendments.
- reserved_qty: derived from authoritative active Reservations net release/consumption.
- shipped_qty: posted Dispatch quantities net reversals.
- invoiced_qty: posted Invoice source quantities net reversals/corrections.
- returned_qty_physical: physical return authority.
- credited_qty_financial: financial credit authority.
- remaining_to_ship: effective ordered qty - net shipped - cancelled remainder.
- remaining_to_invoice: eligible source basis - net invoiced.

No generic ambiguous remaining_qty is authoritative.

## 11. Source-target and reversal

Required links:
- Quote revision line → Sales Order line(s) + converted qty.
- Sales Order effective line/version → Reservations.
- Sales Order line/version → Dispatch lines.
- Dispatch/Order line/version → Invoice lines.
- original posted document/effect → reversal.
- amendment → prior effective order version and changed lines.

Reversal never deletes original history.

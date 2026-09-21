# Sales Workflows

## 1. End-to-end chain

Quote
→ line-level conversion link
→ Sales Order
→ Reservation
→ Dispatch
→ Sales Invoice
→ Collection Link
→ Sales Return Link

Not every link is mandatory in every future policy. Source-less/direct invoice behavior is blocked by SALES-B002.

## 2. Effect matrix

| Object / Action | DOC | RES | STOCK | ACCOUNT | CASH/BANK | COST | Audit | Outbox |
|---|---|---|---|---|---|---|---|---|
| Quote draft/save | CREATE/UPDATE DRAFT | NONE | NONE | NONE | NONE | NONE | CHANGE | optional customer communication only |
| Quote revision | CREATE REVISION/LINK | NONE | NONE | NONE | NONE | NONE | REQUIRED | optional communication |
| Quote accepted | STATE/LINK | NONE | NONE | NONE | NONE | NONE | REQUIRED | optional notification |
| Quote → Order | LINK/CREATE TARGET | NONE | NONE | NONE | NONE | NONE | REQUIRED | NONE |
| Sales Order confirm | STATE | NONE directly | NONE | NONE | NONE | NONE | REQUIRED | optional downstream notification |
| Reservation create | LINK | INCREASE | NONE | NONE | NONE | NONE | REQUIRED | optional operational event |
| Reservation release | LINK | DECREASE | NONE | NONE | NONE | NONE | REQUIRED | optional operational event |
| Dispatch finalize/post | POST | DECREASE/RELEASE | POST OUT | NONE | NONE | UNKNOWN: SALES-B005 | REQUIRED | shipment/integration event where needed |
| Dispatch reverse | REVERSE/LINK | restoration policy by source | REVERSE physical effect | NONE | NONE | UNKNOWN: SALES-B005 | REQUIRED | correction event |
| Sales Invoice finalize/post from Dispatch | POST | NONE | NONE | POST receivable increase | NONE | UNKNOWN: SALES-B005 | REQUIRED | e-document/notification |
| Sales Invoice finalize/post from Order | POST | NONE | NONE | POST receivable increase | NONE | UNKNOWN: SALES-B005 | REQUIRED | e-document/notification |
| Direct/source-less Invoice post | POST | NONE | BLOCKED: SALES-B002 | POST receivable increase only if posting allowed | NONE | BLOCKED | REQUIRED | e-document/notification |
| Invoice reverse | REVERSE/LINK | NONE | NONE for dispatch-sourced invoice | REVERSE receivable | NONE | policy-dependent | REQUIRED | e-document correction where needed |
| Collection post | CREATE/POST finance event | NONE | NONE | DECREASE receivable/balance | INCREASE cash or bank | NONE | REQUIRED | receipt/notification optional |
| Collection allocation | LINK | NONE | NONE | BLOCKED: SALES-B001 | NONE | NONE | REQUIRED if adopted | NONE |
| Sales return physical receipt | LINK/POST return context | NONE | POST IN or non-available status movement by disposition | NONE | NONE | valuation policy later | REQUIRED | return event |
| Sales return credit | LINK/POST finance correction | NONE | NONE | DECREASE receivable | NONE | financial cost policy later | REQUIRED | e-document correction where needed |
| Refund | LINK/POST finance event | NONE | NONE | account effect per credit/settlement context | DECREASE cash/bank | NONE | REQUIRED | payment-provider effect if applicable |
| Proforma | CREATE/LINK | NONE | NONE | NONE | NONE | NONE | CHANGE | PDF/customer communication optional |

## 3. Quote state machine

Planned states supported by current evidence:

| State | Meaning | Editable | Allowed next | Side effect |
|---|---|---:|---|---|
| DRAFT | working proposal | yes | PENDING_INTERNAL_APPROVAL or CUSTOMER_REVIEW or CANCELLED, policy-dependent | DOC only |
| PENDING_INTERNAL_APPROVAL | optional internal review gate | limited | DRAFT/approved path/CANCELLED | no stock/account |
| CUSTOMER_REVIEW | sent/presented to customer | revision through new revision, not history rewrite | ACCEPTED, SUPERSEDED, EXPIRED, CANCELLED | communication only |
| ACCEPTED | customer accepted this revision | no silent economic rewrite | CONVERTED or remain accepted | no stock/account |
| CONVERTED | source relation to Sales Order exists | no | terminal for converted scope; partial behavior SALES-B003 | link only |
| SUPERSEDED | newer revision replaced this revision | no | terminal | history retained |
| EXPIRED | validity ended | no operational conversion unless policy explicitly permits | terminal/policy reopening unknown | none |
| CANCELLED | cancelled before downstream conversion | no | terminal | none |

Mandatory-vs-optional internal approval is SALES-B007.

Revision rule:
- new revision is a new historical revision linked to previous quote/revision;
- prior revision is not silently overwritten after external/customer review;
- conversion records exact source revision and line relation.

## 4. Sales Order state machine

| State | Meaning | Editable | Allowed next | Side effect |
|---|---|---:|---|---|
| DRAFT | unconfirmed order | yes | CONFIRMED / optional approval gate / CANCELLED | DOC only |
| PENDING_APPROVAL | optional policy gate | limited | CONFIRMED / DRAFT / CANCELLED | none |
| CONFIRMED | accepted demand; eligible for downstream operations | amendment policy restricted by SALES-B008 | ON_HOLD / PARTIALLY_COMPLETED / COMPLETED / CANCELLED_REMAINDER | no physical stock/account |
| ON_HOLD | downstream processing blocked | no quantity-history rewrite | CONFIRMED / cancellation policy | no physical effect |
| PARTIALLY_COMPLETED | some quantity reserved/shipped/invoiced while remainder exists | source quantity cannot fall below processed quantity | further reservation/dispatch/invoice / CANCELLED_REMAINDER / COMPLETED | derived status |
| COMPLETED | fulfilment policy says nothing remains to process | no | terminal except correction workflows | none |
| CANCELLED_REMAINDER | unprocessed remaining scope cancelled | no | terminal for cancelled remainder | release active reservation for cancelled quantity |
| CANCELLED | order cancelled before downstream processing | no | terminal | release active reservation |

Whether confirmation auto-creates reservation is SALES-B004.
Whether order is considered completed by shipment, invoice or both is a business decision; until explicitly set, completion should be derived/displayed separately as shipping-complete and invoicing-complete rather than hiding the distinction.

## 5. Reservation lifecycle

Reservation is not a commercial document and not physical inventory movement.

Lifecycle:
- REQUESTED/CREATED: eligible confirmed order line requests quantity.
- ACTIVE: quantity committed against available inventory.
- PARTIALLY_CONSUMED: some reserved quantity has been consumed by posted dispatch.
- RELEASED: manually/order-cancel/remainder-cancel released.
- CONSUMED: fully consumed by valid dispatch.
- CANCELLED: reservation operation invalidated before consumption, with audit.

Required links:
Reservation → Sales Order line → Company → Warehouse (and location/lot only when future Inventory policy requires).

Concurrency invariant:
two concurrent reservations cannot both commit the same available quantity beyond accepted oversell policy. Exact DB mechanism is P3.

## 6. Dispatch state machine

V38 evidence distinguishes draft/picking/planned/finalize/post/carrier handoff.

| State | Meaning | Editable | Allowed next | Side effect |
|---|---|---:|---|---|
| DRAFT | shipment preparation | yes | PICKING / CANCELLED | none |
| PICKING | warehouse gathering goods | operational fields | PACKED/READY or back to draft by policy | no authoritative stock-out |
| READY | quantities/packages validated and ready to finalize | limited | POSTED / CANCELLED | none |
| POSTED | dispatch finalized; physical goods issued | no line mutation | HANDED_OVER / DELIVERED / REVERSED | STOCK OUT + reservation consumption |
| HANDED_OVER | carrier/ambar handoff recorded | no | DELIVERED / exception | no second stock-out |
| DELIVERED | delivery status recorded | no | return/correction link only | no new stock effect |
| REVERSED | compensating physical reversal linked to posted dispatch | no | terminal | reverse inventory effect |
| CANCELLED | pre-post shipment cancelled | no | terminal | release associated temporary allocations as defined |

Physical recognition point:
POSTED / Sevkiyatı Kesinleştir. Picking, packing or carrier fields alone do not reduce stock.

Partial:
one order line may feed multiple dispatch lines; each posted dispatch contributes only its own quantity.

## 7. Sales Invoice state machine

| State | Meaning | Editable | Allowed next | Side effect |
|---|---|---:|---|---|
| DRAFT | financial document preparation | yes | POSTED / CANCELLED | none |
| POSTED | finalized invoice | no | e-document send state / REVERSED | ACCOUNT receivable increase |
| REVERSED | linked financial reversal/correction | no | terminal; replacement invoice may be separate | reverse account effect |
| CANCELLED | draft cancelled before posting | no | terminal | none |

E-document provider status must not be conflated with financial POSTED state. A provider send failure after local posting leaves invoice posted and creates observable integration failure/retry state.

Invoice source modes:
- Dispatch-sourced: allowed; invoiceable quantity comes from eligible posted dispatch lines; STOCK = NONE.
- Order-sourced: V38 Sales Order exposes Fatura Oluştur; allowed as a commercial/financial link. It does not by itself create physical STOCK OUT.
- Source-less/direct: draft UI exists, but posting stock semantics are BLOCKED by SALES-B002.

## 8. Collection linkage

Collection is a Finance-owned event.

Locked:
- customer account receivable/balance decreases;
- cash or bank increases;
- partial collection is conceptually supported;
- collection is not part of invoice posting transaction.

Blocked:
- whether collection allocates to specific invoice(s), remains unallocated/advance, or only changes total balance: SALES-B001.

Therefore Sales Invoice UI must not infer paid/unpaid from an unapproved allocation model.

## 9. Sales return linkage

PLAN-002 defines only the Sales-side linkage.

Physical path:
source dispatch/order/invoice reference
→ return authorization/RMA context
→ receipt
→ inspection/disposition
→ AVAILABLE / QUARANTINE / REWORK / SCRAP / DAMAGED handling.

Financial path:
source invoice/customer reference
→ credit note/financial correction
→ receivable reduction
→ optional refund as separate cash/bank event.

Physical receipt does not automatically imply financial credit, and financial credit does not prove physical receipt.

## 10. Quantity contract

Authoritative/derived planning semantics:

- ordered_qty: authoritative commercial quantity on the accepted Sales Order line/revision.
- reserved_qty: derived from active reservation records/events net of release/consumption; not copied as master truth.
- shipped_qty: derived from posted Dispatch source-line quantities net of dispatch reversals.
- invoiced_qty: derived from posted Invoice source-line quantities net of invoice reversals/credit corrections according to source mode.
- returned_qty_physical: derived from posted physical return receipts/dispositions.
- credited_qty_financial: separate from physical returned quantity.
- remaining_to_ship: derived from effective ordered quantity minus net shipped/cancelled remainder according to accepted amendment/cancellation policy.
- remaining_to_invoice: derived from the relevant invoice source basis minus net invoiced quantity.
- a single ambiguous remaining_qty should not hide the difference between remaining-to-ship and remaining-to-invoice.

Invariants:
- net shipped quantity cannot exceed the permitted effective order quantity unless an explicit future tolerance policy exists.
- invoice quantity cannot exceed eligible source quantity.
- cancelled remainder is not processable.
- reversed downstream quantity returns to the appropriate derived remaining calculation without deleting original records.
- quantities are decimal/NUMERIC in future persistence, never float.

## 11. Source/target line links

Required conceptual links:
- Quote revision line → Sales Order line(s), conversion quantity if partial conversion is adopted.
- Sales Order line → Reservation entries.
- Sales Order line → Dispatch line(s).
- Dispatch line and/or Sales Order line → Invoice line(s), depending source mode.
- Sales Invoice/Dispatch/Order line → Return/RMA line.
- Original posted document/ledger entry → reversal entry.

Every line link must preserve company scope and exact source quantity basis.

## 12. Historical snapshots

At the accepted legal/operational freeze point, preserve as applicable:
- customer legal/trade name;
- tax identity;
- billing address;
- shipping address;
- contact/recipient delivery fields needed historically;
- product code/name;
- variant/configuration/requirement snapshot where relevant;
- UOM;
- unit price;
- discount data;
- tax rate and tax treatment;
- currency;
- exchange rate and rate metadata once SALES-B006 is resolved;
- quote/order revision/source document references;
- project/architect attribution when the source workflow carries it.

Master changes must not mutate posted/history documents.

## 13. Idempotency and duplicate protection

Required future idempotency candidates:
- dispatch finalization/post;
- invoice finalization/post;
- dispatch/invoice reversal;
- external order-to-Sales-Order creation;
- e-document outbox consumption;
- return financial posting;
- collection posting in Finance.

A repeated request must return/identify the existing logical result or deterministic conflict, never create duplicate business effects.

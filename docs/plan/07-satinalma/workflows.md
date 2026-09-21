# Purchasing Workflows

Status: FROZEN — PLAN-005

## 1. End-to-end

Purchase Order
→ partial Goods Receipt / Service Acceptance
→ Quality/Disposition where applicable
→ Supplier Invoice match
→ Finance Payment

Purchase Return links physical and financial corrections without merging them.

## 2. Purchase Order lifecycle

DRAFT
→ PENDING_APPROVAL when policy exception exists
→ APPROVED
→ SENT
→ ON_HOLD / CLOSED / CANCELLED_REMAINDER.

DRAFT may be CANCELLED.

Rules:
- PO has no STOCK/ACCOUNT/CASH effect.
- supplier = same-company ACTIVE SUPPLIER Party.
- line = active PURCHASABLE Product/Variant/UOM.
- standard policy-compliant PO may proceed without manual approval.
- exception approval requires creator != approver.
- sent/approved history is versioned/audited; processed scope is not silently rewritten.

Progress is derived separately:
- receipt progress;
- invoice progress.

## 3. Goods Receipt

DRAFT → POSTED → REVERSED.

For stockable line at POST:
1. validate same-company PO/supplier/product/warehouse;
2. validate eligible ordered remainder + tolerance policy;
3. capture entered UOM and base conversion;
4. require lot/serial/expiry when Product tracking requires;
5. create physical Inventory Ledger STOCK IN;
6. initial disposition = QUARANTINE;
7. update receipt source-target quantity projection;
8. generate/attach QC requirement where active policy requires.

Supplier payable remains unchanged.

For SERVICE/NON-STOCK:
- optional receipt/acceptance evidence;
- STOCK = NONE.

## 4. Receipt release / QC

Goods Receipt POST does not imply AVAILABLE.

Flow:
QUARANTINE
→ inspection/disposition if required
→ AVAILABLE / QUALITY_HOLD / REWORK / DAMAGED / return path.

No-inspection policy may permit immediate separate RELEASE action after POST.

QC result and inventory disposition are related but not the same record/truth.

## 5. Partial / over / short receipt

Short/partial:
- always allowed unless PO line itself has a stricter later policy;
- remainder stays open.

Exact:
- cumulative received <= ordered: normal.

Over:
- default BLOCK.
- if policy maximum > 0:
  - amount within maximum enters tolerance exception;
  - approval required;
  - amount above maximum blocked.

Cancelled remainder cannot later be received.

## 6. Supplier Invoice lifecycle

DRAFT
→ MATCH_PENDING
→ MATCHED or MATCH_EXCEPTION
→ PENDING_APPROVAL if exception requires approval
→ POSTED
→ REVERSED.

Pre-post DRAFT may be CANCELLED.

At POST:
- supplier payable increases;
- STOCK = NONE;
- immutable tax/FX/commercial snapshot freezes.

## 7. Invoice source modes

### Stockable receipt-sourced

Required:
PO line + posted Goods Receipt line + Supplier Invoice line.

3-way match:
- supplier/company
- Product/Variant
- UOM/conversion
- quantity
- price/discount
- tax
- currency

Normal invoiceable qty:
net eligible posted receipt qty - net already invoiced qty.

### Service/non-stock PO-sourced

2-way match:
PO line + Invoice line.

Optional acceptance evidence may be linked.

### Direct financial-only

Only SERVICE/NON-STOCK.

Required:
- explicit permission;
- reason;
- approval.

No PO/Receipt stock effect.
STOCK = NONE.

## 8. Match exception

Match statuses:
PENDING / MATCHED / EXCEPTION / APPROVED_EXCEPTION / BLOCKED / NOT_REQUIRED.

Default tolerances:
- over-receipt: 0
- over-invoice: 0
- price variance: 0

Configured policy may define maximums.

Any non-zero overage/price variance:
- recorded;
- requires exception approval.

Beyond maximum:
- BLOCKED.

Approval binds exact invoice/match snapshot; edit invalidates/re-evaluates approval.

## 9. Quantity semantics

PO line:
- ordered_qty
- received_qty
- invoiced_qty
- returned_qty_physical
- remaining_to_receive
- remaining_to_invoice

`received_qty` uses net posted receipt after reversal.

For stockable:
`remaining_to_receive = effective_ordered - net_received - cancelled_remainder`

`remaining_to_invoice = net_eligible_received - net_invoiced`

For service/non-stock PO-sourced:
`remaining_to_invoice = effective_ordered/value basis - net_invoiced`

Exact quantity/value basis is line contract, not a generic ambiguous remaining field.

## 10. Calculation / FX

Supplier Invoice:
- KDV-exclusive;
- line discount;
- document discount allocation;
- taxable base;
- line tax;
- currency-minor-unit rounding;
- midpoint away-from-zero.

FX:
- default TCMB döviz alış;
- invoice/tax-event date;
- prior published business day fallback;
- manual override permission + reason + approval.

Posted snapshot never follows future master/rate change.

## 11. Payment

Supplier Invoice may offer "Ödeme" navigation/action.

Actual event is Finance:
- payable decrease;
- cash/bank OUT;
- no stock.

Purchasing does not decide:
- invoice allocation;
- advance allocation;
- netting;
- reconciliation.

Those are PLAN-007 Finance contracts.

## 12. Purchase Return

Physical:
Goods Receipt/lot/serial → Purchase Return shipment → STOCK OUT.

Financial:
Supplier Invoice → debit/credit adjustment → payable reduction/reversal.

They are independently traceable.

Return completion UI reports:
- physical returned;
- financial adjusted;
- remaining action.

## 13. Reversal and cancellation

PO:
- unprocessed remainder may be cancelled;
- processed history retained.

Goods Receipt:
- POSTED cannot be deleted;
- reverse with compensating physical movement;
- downstream conflicts must be resolved first.

Supplier Invoice:
- POSTED cannot be edited/deleted;
- reverse financial posting;
- does not reverse stock because invoice never owned stock.

## 14. Source-target links

Required line-level links:
- PO → Goods Receipt;
- PO → Supplier Invoice;
- Goods Receipt → Supplier Invoice for stockable 3-way match;
- Goods Receipt → Purchase Return;
- Supplier Invoice → financial return adjustment;
- original posted document → reversal.

Every quantity-bearing link stores exact processed quantity/UOM/base normalization.

## 15. Concurrency/idempotency risks

Future durable implementation must prevent:
- same PO remainder received twice concurrently;
- duplicate Goods Receipt POST;
- same receipt quantity invoiced twice;
- duplicate Supplier Invoice POST;
- stale tolerance approval;
- return exceeding eligible received quantity;
- reversal against changed downstream state.

Exact DB locking/constraints belong P3.

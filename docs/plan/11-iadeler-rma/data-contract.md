# Returns / RMA Conceptual Data Contract

Status: FROZEN logical/domain planning only. No SQL/schema/index/type is defined.

## 1. Ownership
Returns authoritative:
- Return/RMA case identity;
- line authorization;
- source/target lineage;
- reason/evidence;
- physical/financial/refund progress references and coordination state.

Inventory authoritative:
- physical movement, location, disposition, lot/serial quantity truth.

Finance authoritative:
- Account/Cash/Bank ledgers;
- refund transactions;
- Inventory Valuation / Cost Ledger;
- FX/period effects.

Sales/Purchasing remain authority for original source documents.

## 2. Conceptual records
### Return Case
company, applicable branch, direction, Party + role, case/reference, lifecycle, reason, source mode LINKED/EXCEPTION_SOURCELESS, approval/audit, accepted Party snapshot.

### Return Line
Product/Variant/UOM snapshot, authorized quantity, source lineage, accepted conversion snapshot, lot/serial requirements, reason/condition metadata.

### Return Source Link
Normalized quantity/value-bearing relation from exact Sales/Purchasing source document/line/version/movement to return line.
No comma-separated IDs.
One return line may reference multiple eligible source portions only when each quantity-bearing link is explicit.

### Physical Processing Reference
Return line → Inventory movement(s), quantity, warehouse/location/disposition and reversal lineage.
It is a reference, not copied stock authority.

### Financial Processing Reference
Return line/case → Finance adjustment/refund/valuation transaction(s), amount/quantity basis, currency and reversal lineage.
It is not copied Account/Cash/Bank authority.

### Replacement Link
Return case/line → normal Sales document identity.
It does not own replacement quantities or postings.

## 3. Quantity invariants
- authorized quantity > 0;
- movement quantity > 0;
- comparable quantity normalized using accepted decimal UOM conversion snapshot;
- net source-linked physical returned <= eligible source physical quantity;
- physical remaining = authorized eligible physical quantity - net physical processed;
- financial quantity/value remaining is independently derived from financial basis;
- reversal restores eligible remainder through linked compensation;
- no generic ambiguous returned_qty is authority across both dimensions.

## 4. Source identity
Customer normal physical source: posted Sales Dispatch lineage.
Customer financial basis may reference posted Sales Invoice/correction context.
Supplier normal physical source: posted Goods Receipt lineage.
Supplier financial basis may reference posted Supplier Invoice/correction context.
Order references are context, not physical movement proof.

## 5. Source-less
Source-less case stores explicit exception classification, reason, evidence, approver and valuation/financial basis references where applicable.
It never creates a fake historical source link.

## 6. Snapshots
Freeze accepted Party, Product/Variant/UOM/conversion, source identity/version, currency/commercial basis where relevant, lot/serial evidence and return reason/condition.
Live master edits never rewrite accepted history.

## 7. Financial semantics
Customer credit = CUSTOMER_RECEIVABLE CREDIT.
Customer refund = CUSTOMER_RECEIVABLE DEBIT + Cash/Bank OUT.
Supplier adjustment = SUPPLIER_PAYABLE DEBIT.
Supplier refund = SUPPLIER_PAYABLE CREDIT + Cash/Bank IN.
No Invoice allocation/open-item authority.
No automatic cross-role netting.

## 8. Valuation
Physical quantity truth never lives in valuation records.
Purchase return valuation follows current moving-average outbound policy.
Customer return cost restoration/correction uses Finance-owned source lineage/cost policy.
Source-less positive inbound with no valid basis requires explicit approved valuation; silent zero cost forbidden.

## 9. State/history
Posted physical/financial history append/reversal oriented.
Current progress may be projected but cannot overwrite movement truth.
Case completion is derived from required dimensions; it is not evidence that every dimension had an effect.

## 10. Scope
Company mandatory.
Warehouse scope belongs physical processing.
Branch scope follows source/financial ownership where required.
Cross-company references forbidden.

## 11. P3 concurrency/idempotency risks
Protect:
- duplicate case/reference acceptance;
- concurrent returns against same source remaining;
- same serial returned twice;
- duplicate physical receipt/shipment;
- duplicate financial credit/adjustment/refund;
- physical post racing cancellation/reversal;
- QC/disposition racing receipt reversal;
- refund racing credit reversal;
- supplier shipment racing stock movement;
- stale case transition;
- duplicate reversal;
- cross-company source/Party/warehouse/account links.
Valkey cannot be sole correctness guarantee.

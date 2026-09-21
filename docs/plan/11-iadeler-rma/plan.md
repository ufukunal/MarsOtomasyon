# Returns / RMA Domain Plan

Status: COMPLETED / FROZEN — PLAN-009

## 1. Objective
Freeze customer/supplier return authorization, physical execution, QC/disposition, financial credit/refund, partial processing, source lineage and reversal before logical database planning.

## 2. Authority boundary
Returns owns:
- return/RMA case identity and lifecycle;
- return line intent/authorization;
- source/target lineage;
- reason/evidence;
- coordinated physical-progress and financial-progress references.

Inventory/Warehouse owns:
- Inventory Ledger;
- physical quantity/location/disposition;
- lot/serial custody.

Finance owns:
- CUSTOMER_RECEIVABLE / SUPPLIER_PAYABLE Account Ledger;
- Cash/Bank Ledger;
- refund transactions;
- Inventory Valuation / Cost Ledger;
- FX and posting periods.

Sales/Purchasing retain original commercial source authority.
Return projections/reports are rebuildable.

## 3. Source policy
### Source-linked normal path
Customer return line references eligible Sales source lineage, normally posted Dispatch for physical quantity and posted Sales Invoice where a financial credit is requested.
Supplier return line references eligible posted Goods Receipt for physical quantity and Supplier Invoice context where a financial adjustment is requested.

Order-only references may provide context but cannot prove a physical quantity that was never shipped/received.

### Controlled source-less exception
Allowed only when:
- Party/Product/company identity is valid;
- explicit source-less-return permission exists;
- reason and evidence are mandatory;
- approval is required;
- physical and financial eligibility are independently validated;
- no historical source is fabricated.

For source-less customer physical receipt, accepted quantity enters QUARANTINE and Finance valuation basis must be explicit/approved if no valid cost lineage exists.
A source-less financial credit is not inferred from physical receipt; it requires Finance-approved adjustment basis.

## 4. Customer return
Authorization/RMA has no STOCK/ACCOUNT/CASH effect.

Physical receipt POST:
- STOCK IN once through Inventory Ledger;
- initial disposition QUARANTINE;
- no automatic CUSTOMER credit;
- no automatic Cash/Bank refund.

QC/disposition is separate:
QUARANTINE → AVAILABLE / QUALITY_HOLD / REWORK / DAMAGED / SCRAP path.
SCRAP physical OUT is Warehouse-owned explicit action, not hidden inside RMA receipt.

Financial credit POST:
- CUSTOMER_RECEIVABLE CREDIT;
- STOCK NONE;
- Cash/Bank NONE;
- amount/quantity basis linked to eligible return context;
- no Invoice allocation/open-item state.

Customer refund:
- Finance-owned transaction against eligible customer credit/advance balance;
- CUSTOMER_RECEIVABLE DEBIT;
- Cash/Bank OUT;
- no STOCK effect.

Physical receipt, financial credit and refund can complete at different times.

## 5. Supplier return
Authorization has no STOCK/ACCOUNT/CASH effect.

Physical supplier return shipment POST:
- STOCK OUT once through Inventory Ledger;
- source Goods Receipt/lot/serial lineage retained;
- no automatic SUPPLIER financial adjustment;
- no Cash/Bank effect.

Financial supplier adjustment POST:
- SUPPLIER_PAYABLE DEBIT;
- STOCK NONE;
- Cash/Bank NONE.

Supplier refund:
- Finance-owned transaction against eligible supplier advance/debit position;
- SUPPLIER_PAYABLE CREDIT;
- Cash/Bank IN;
- no STOCK effect.

Physical shipment, financial adjustment and supplier refund can complete independently.

## 6. Quantity and partial rules
For each source-linked return line:
- authorized_qty;
- physically_processed_qty;
- financially_adjusted_qty where quantity-based;
- remaining_physical_qty;
- remaining_financial_qty where meaningful.

Normal physical cap:
cumulative net physical return <= eligible source physical quantity net prior returns/reversals.

Financial adjustment cap is independently validated against eligible financial basis; physical completion does not automatically authorize a larger financial credit.

Partial return is allowed.
Over-return is blocked by default. Any business exception beyond source eligibility must use source-less/exception authorization and cannot mutate original source quantities.

## 7. Product/UOM/lot/serial
Return must preserve compatible Product/Variant identity.
Comparable quantities normalize using accepted source UOM conversion snapshot.
Lot-tracked return requires valid lot lineage or approved source-less evidence.
Serial return requires exact serial identity; same serial cannot be received/returned twice concurrently.
Substitution of another product/serial to satisfy an existing source line is forbidden.

## 8. QC/disposition
Customer returned stock is QUARANTINE-first.
Disposition does not itself create Customer credit/refund.
AVAILABLE requires accepted release.
QUALITY_HOLD/REWORK/DAMAGED remain on-hand but non-available.
SCRAP requires explicit Warehouse STOCK OUT and Finance write-off valuation.

Supplier return eligibility may consume AVAILABLE or approved eligible non-available stock according to Warehouse policy; invalid custody/status blocks shipment.

## 9. Financial valuation
Purchase return physical OUT removes current carrying value using PLAN-007 moving weighted-average valuation and creates Finance PURCHASE_RETURN_VALUE_OUT lineage.

Customer return:
- Returns preserves Sales Dispatch/Invoice/cost lineage where available;
- Finance determines the inventory-value restoration and COGS/bridge correction consistent with original source and current frozen Finance costing rules;
- original historical COGS/dispatch values are not rewritten;
- source-less return with no valid cost basis requires explicit approved valuation input; silent zero-cost inbound is forbidden.

QC status-only movement does not by itself revalue inventory.
Later scrap/write-off follows Finance/Warehouse rules.

## 10. FX
Return financial correction preserves original source currency/commercial snapshots and records the accepted correction transaction/base values under Finance policy.
Cash/Bank refund uses actual refund-date Finance FX/carrying rules.
Realized FX belongs Finance.
Original Sales/Purchasing snapshots are immutable.

## 11. Replacement / exchange
No second exchange-sales engine is created.
Replacement/exchange is:
1. a normal Return/RMA case for returned goods; plus
2. a separately authorized normal Sales Order/Dispatch/Invoice flow for replacement goods, linked for navigation/audit.
Return completion never silently dispatches replacement stock or creates a replacement invoice.

## 12. Cancellation/reversal
Unposted authorization may cancel.
Posted physical movement uses Inventory reversal/compensation.
Posted financial credit/adjustment/refund uses Finance reversal/compensation and period controls.
Later dependent disposition, scrap, replacement or refund may block simple reversal and require explicit compensation chain.
Original records remain.

## 13. Scope
Company mandatory.
Branch/warehouse enforced where physical or financial ownership requires.
Cross-company return/source/Party/warehouse/account links forbidden.
No automatic CUSTOMER/SUPPLIER role netting.

## 14. V38 classification
The checked V38 reference exposes no discoverable Return/RMA-specific labels; therefore no return-specific UI behavior is frozen from V38.
Existing Sales/Purchasing/Inventory/Finance contracts are the authoritative predecessors.

# Returns / RMA Permissions and SoD

Status: FROZEN planning contract.

## 1. Permission families
- returns.read
- returns.create
- returns.authorize
- returns.source_less
- returns.customer_receive
- returns.qc_disposition
- returns.supplier_ship
- returns.financial_adjust
- returns.refund_launch
- returns.replace
- returns.reverse
- returns.approve
- returns.export

Inventory and Finance postings additionally require their applicable scoped permissions.

## 2. Scope
Every action enforces company.
Physical actions enforce warehouse/location/lot/serial scope.
Financial actions enforce Party role, branch/account and Finance period scope where applicable.
Cross-company source/target is forbidden.

## 3. Approval
Approval policy evaluates:
- source-less return;
- over-source exception;
- manual valuation;
- material financial adjustment exception;
- high-risk reversal;
- replacement exception where company policy requires.

When approval is required:
creator/initiator != approver.
Approval binds exact case/version/lines/quantities/amounts/currency/source/warehouse/target action.
Material edit invalidates approval.

## 4. Separation of duties
Return authorization alone cannot grant Finance refund posting.
Warehouse receipt/shipment permission alone cannot grant financial adjustment.
Finance adjustment permission cannot create physical stock.
Source-less permission is separate from normal return creation.

## 5. Reversal
Reverse permission is distinct.
Reason and dependency checks mandatory.
Posted effects are compensated by owning Inventory/Finance modules.

## 6. Audit
Record actor/time, company/branch/warehouse, case/line, source, Party role, Product/UOM/lot/serial, action, quantity/amount/currency, before/after state, Inventory/Finance references, reason/evidence, approval, correlation/idempotency and reversal lineage.

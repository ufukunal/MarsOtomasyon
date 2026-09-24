# Subcontracting / Fason — Detailed Plan

Status: DETAILED PLANNING FROZEN

## V38 product surface
Fason Emirleri, Yeni Fason Emri, Malzeme Gönder, Fason Çıktı Kabul, Malzeme İade, Fason Mutabakatı.

## Ownership
Subcontracting owns operational subcontract order and reconciliation. Inventory owns physical material/output movements. Purchasing owns supplier commercial/service procurement. Production owns parent production requirement. Finance owns service cost and valuation.

## Records
SubcontractOrder, OrderLine/Requirement, MaterialSend, MaterialSendLine, SubcontractReceipt, ReceiptLine, MaterialReturn, Scrap/LossEvidence, Reconciliation, ServiceSourceLink.

## Workflow
DRAFT -> APPROVED/RELEASED -> MATERIAL_SENT -> PARTIALLY_RECEIVED -> RECEIVED -> RECONCILED -> CLOSED; cancellation only before irreversible processing, later correction via linked reversal/adjustment.

## Effects
Send company-owned material: Inventory movement to controlled subcontract/transit/custody position, not Sales.
Receipt output: Inventory IN/controlled internal transformation according to source.
Unused material return: Inventory movement back.
ACCOUNT/CASH: none directly.
COST: service/variance handed to Purchasing/Finance.

## Rules
Exact Production/Purchase source when applicable; cumulative sent/received/returned/scrapped balance must reconcile; lot/serial lineage preserved; no negative subcontract custody; partial send/receipt supported.

## Permissions/API/UI
subcontract.order.*, .send, .receive, .return, .reconcile, .approve.
Routes /api/v1/subcontract/orders/{id}/send|receive|return|reconcile.
V38 tabs/actions preserved with timeline and discrepancy view.

## Data/concurrency
Normalized lines and movement links; same-company supplier/product/UOM/warehouse; durable idempotency; source caps and reconciliation locked transactionally.

## Acceptance
Partial send/receive, return, loss/scrap evidence, no Sales effect, Inventory linkage, source cap, reconciliation and permission tests.

## UNKNOWN
Exact subcontract location representation and accepted loss/tolerance approval thresholds are not specified by V38; choose only when implementation reaches this module.

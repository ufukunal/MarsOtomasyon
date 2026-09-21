# Returns / RMA Integration Contract

Status: FROZEN planning contract. No provider implementation.

## 1. Boundary
Returns coordinates commands but never bypasses owning ledgers.

For a physical return command:
validate case/source/state/remaining/permission/idempotency
→ one PostgreSQL transaction records return processing reference + authoritative Inventory movement
→ outbox after commit where needed.

For a financial command:
validate case/basis/state/permission/period/idempotency
→ one PostgreSQL transaction records return financial reference + authoritative Finance effects
→ outbox after commit.

Physical and financial commands need not occur in one transaction because they are intentionally independent business events.

## 2. Sales
Customer return preserves exact Dispatch source for physical eligibility and Sales Invoice context for financial correction where applicable.
Replacement uses normal Sales workflow and explicit link only.
Returns cannot post Sales Dispatch/Invoice itself.

## 3. Purchasing
Supplier return preserves Goods Receipt physical source and Supplier Invoice financial context where applicable.
Returns cannot mutate PO/Receipt/Invoice history.

## 4. Inventory/Warehouse
Inventory owns STOCK IN/OUT, lot/serial, location and disposition.
Customer receipt starts QUARANTINE.
Supplier shipment consumes eligible current custody.
QC/disposition references return case but remains physical authority.

## 5. Finance
Finance owns Account/Cash/Bank and valuation.
Return credit/adjustment and refund are separate transactions.
Posting periods, FX, carrying values and reversals use PLAN-007 rules.

## 6. Quality
Future Quality module may provide inspection evidence/results.
Until implemented, PLAN-009 freezes only the required disposition contract; it does not invent a separate Quality schema/provider.
Quality result cannot silently post Account/Cash effects.

## 7. External/e-commerce
Future channel/provider return request may enter staging/evidence and map to a Mars Return case.
Provider state is not authority for Inventory/Finance.
Duplicate webhook/import must be idempotent.
Provider capability remains implementation-time verification.

## 8. Files
Photos, damage evidence, shipping/proof and approvals may link through shared Files abstraction later.
Files are evidence, not ledger truth.

## 9. Candidate events
- ReturnAuthorized
- CustomerReturnReceived
- ReturnDispositionChanged
- CustomerReturnCredited
- CustomerRefundPosted
- SupplierReturnShipped
- SupplierReturnAdjusted
- SupplierRefundPosted
- ReturnLinkedToReplacement
- ReturnMovementReversed

Events carry stable identities/references, not mutable balances.

## 10. Observability
Trace case/line, company/branch/warehouse, Party, Product/lot/serial, source, quantity, financial amount/currency, Inventory/Finance transaction, actor, operation/idempotency and correlation IDs.

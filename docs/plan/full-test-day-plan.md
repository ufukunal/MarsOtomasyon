# Full Test Day — Detailed Execution Plan

Status: DETAILED PLANNING FROZEN / EXECUTION DEFERRED

## Entry gate
Implementation intended for release is materially complete and owner explicitly starts Full Test Day.

## Functional suites
Full unit/regression; PostgreSQL integration; API contract/OpenAPI; browser E2E; Web/Desktop/Mobile journeys; provider sandbox where configured.

## Cross-module ERP invariants
Sales, Purchasing, Inventory, Warehouse, Finance, Returns, Checks/Notes, Quality, Production, subcontract/import, service/consignment/contracts.
Verify exact source caps, partial processing, reversal chains and no duplicate physical/financial effects.

## Inventory invariants
No unauthorized negative stock; Reservation nonphysical; Lot/Serial uniqueness/lineage; transfer TRANSIT; count adjustments; quarantine/disposition; return/reversal; production/fason effects.

## Financial invariants
Receivable/payable debit-credit directions; collection/payment; Cash/Bank; transfer/FX; valuation; Dispatch cost bridge/COGS when implemented; returns; posting periods; reversal; no silent edit.

## Security
Authentication/OIDC; permission matrix; company isolation/IDOR; Warehouse/resource scope; SoD; PII; file security; provider/webhook signatures; secrets; session/device revocation.

## Concurrency/idempotency
Duplicate create/post/reverse; Quote/Order/PO/source caps; Reservation; Dispatch/Receipt/Invoice; Finance ledger; serial; count; webhook; scheduler; offline retry.

## Resilience
Outbox/worker retry, process restart, PostgreSQL reconnect, Valkey loss, provider timeout/replay, partial deployment recovery.

## Data lifecycle
Migration upgrade, pending model, forward-fix/rollback rehearsal where safe, backup, restore, retention/archive, migration from supported legacy datasets.

## Performance
Search/lookup, list pagination, ledgers, inventory positions, reports, posting concurrency, worker queues, provider sync and capacity baseline.

## Devices/providers
Scanner, offline, print formats/routes, marketplace/communications sandbox, reconciliation.

## Evidence
Every suite records commit SHA, environment, run id, seed/data assumptions, pass/fail, defect links and accepted exceptions. No verbal PASS without evidence.

## Exit
All release blockers closed or explicitly owner-accepted; canonical Full Test Day report committed.

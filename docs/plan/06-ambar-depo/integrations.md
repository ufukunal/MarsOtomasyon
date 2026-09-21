# Warehouse Integration Contract

Status: FROZEN planning contract. No device/carrier/provider implementation.

## 1. Transaction/outbox rule

Authoritative physical mutation:
command validation
→ PostgreSQL business/work state + Inventory Ledger
→ outbox in same transaction where an external/realtime effect is required.

Scanner, mobile client, carrier or UI state never determines inventory truth.

## 2. Candidate events

- GoodsReceiptDispositionChanged
- PutAwayCompleted
- PickWorkCompleted
- PackageCompleted
- DispatchReadyForPost
- WarehouseTransferIssued
- WarehouseTransferPartiallyReceived
- WarehouseTransferReceived
- WarehouseTransferReconciliationRequired
- StockCountStarted
- StockCountReviewed
- StockCountAdjustmentPosted
- WarehouseScrapPosted
- OfflineWarehouseOperationConflicted

Sales/Purchasing-owned events remain in their modules.

Payloads use stable document/work identities and minimal source dimensions.

## 3. Sales integration

Warehouse consumes:
- confirmed Sales Order/Dispatch;
- Reservation;
- Product/UOM;
- customer/shipping operational context where needed.

Warehouse produces pick/pack/load readiness.

Sales Dispatch POST remains owner of:
- Dispatch state;
- STOCK OUT;
- Reservation consumption/release.

Warehouse must not emit a separate second outbound stock effect for the same Dispatch.

## 4. Purchasing integration

Goods Receipt POST creates STOCK IN → QUARANTINE.

Warehouse consumes that posted receipt for:
- QC/disposition;
- put-away;
- return execution.

Put-away cannot repeat Goods Receipt stock-in.

## 5. Quality integration

Quality may create inspection/result evidence.

Warehouse/Inventory owns physical disposition command.

QC result:
- input to allowed disposition;
- not itself an Inventory Ledger movement until an authorized disposition action is posted.

A Quality retry must not duplicate a disposition movement.

## 6. Barcode / device

Scanner input resolves via Product barcode authority.

Validate:
- Product/Variant;
- UOM/package;
- Location;
- lot/serial;
- work/document context.

Wrong scan is rejected.

Device adapters supply input only and cannot bypass domain command authorization.

## 7. Offline sync

Client generates durable `client_operation_id` per intentional mutation.

Retry:
same operation id + same logical request → prior result/idempotent handling.

Intentional repeated action:
new operation id.

On sync, server revalidates:
- permission;
- company/Warehouse;
- current work/document state;
- expected version;
- quantity availability;
- lot/serial current state.

Stale conflict:
- no automatic overwrite;
- returned as explicit conflict with current server context sufficient for operator resolution.

Queue order cannot be assumed safe if dependent operations conflict; server business dependencies win.

## 8. Carrier / label

Packing/loading may later integrate:
- carrier;
- label;
- tracking;
- printer.

Provider failure:
- does not change Inventory Ledger;
- does not mark Dispatch POSTED;
- remains retryable external status.

Exact carrier/provider capability requires implementation-time verification.

## 9. Realtime

SignalR may notify:
- new work assignment;
- Reservation/Dispatch change;
- count conflict;
- transfer receive;
- offline conflict.

Realtime message is advisory.
Client refreshes authoritative state before mutation.

## 10. Count / Finance integration

COUNT_ADJUSTMENT posts quantity through Inventory authority.

For positive adjustment/value-sensitive posting, Finance/Costing supplies accepted valuation policy/result when implementation requires it.

Warehouse must not invent zero-cost or arbitrary cost value.

Finance failure must not be hidden; exact atomic/coordination strategy is frozen later when Finance/P3 model is available.

## 11. Scrap / Finance

Warehouse scrap posts physical quantity OUT.

Financial write-off/value handling is Finance-owned.

Cross-module linkage preserves same source scrap/disposal identity without Warehouse posting finance ledger itself.

## 12. Idempotency candidates

Durable idempotency required for:
- Dispatch POST;
- Transfer ISSUE;
- Transfer RECEIVE;
- Count POST;
- Scrap POST;
- offline mutation sync;
- device retry causing business command retry.

Valkey alone cannot guarantee these effects.

## 13. Reconciliation / observability

Observe:
- work/document;
- company/Warehouse/device;
- operation id;
- source/target;
- result/conflict;
- retry count;
- correlation;
- outbox status.

Reconciliation compares projections/device/provider state with PostgreSQL authority; it never blind-overwrites ledger history.

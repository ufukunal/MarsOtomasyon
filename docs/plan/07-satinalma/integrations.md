# Purchasing Integration Contract

Status: FROZEN planning contract. No provider implementation.

## 1. General pattern

Authoritative transaction
→ PostgreSQL + outbox
→ Worker
→ external/provider consumer.

Provider/network success never substitutes for local Purchase/Inventory/Finance truth.

## 2. Candidate events

- PurchaseOrderApproved
- PurchaseOrderSent
- PurchaseOrderRemainderCancelled
- GoodsReceiptPosted
- GoodsReceiptReversed
- GoodsReceiptReleased
- PurchaseMatchExceptionRequested
- PurchaseMatchExceptionApproved
- SupplierInvoicePosted
- SupplierInvoiceReversed
- PurchaseReturnPosted
- PurchaseReturnFinancialAdjustmentRequested

Events reference stable company/document/version/source identities.

## 3. Supplier communication

PO send may use Communications/provider adapter later.

Failure:
- does not alter PO approval/business truth;
- send state/error remains separate and retryable.

## 4. Goods Receipt / Inventory

Goods Receipt POST must atomically persist:
- receipt POST state;
- authoritative Inventory Ledger movement;
- source link;
- outbox where required.

External barcode/scanner/provider data only supplies input; it cannot create stock outside authorized POST command.

## 5. Quality

Receipt may emit QC/inspection request based on active policy.

V38-guided flow:
Goods Receipt POST → QUARANTINE → QC/disposition → AVAILABLE or other disposition.

Quality result is not Purchasing stock authority.

## 6. Supplier Invoice / Finance

Supplier Invoice POST atomically creates:
- Invoice POST state;
- account payable Finance posting/reference;
- immutable calculation/match snapshot;
- outbox.

STOCK remains NONE.

Provider/e-document failure after local POST:
- cannot duplicate payable;
- cannot create stock;
- provider state retries separately.

## 7. Payment

Purchasing may deep-link/request Finance Payment.

Finance owns:
- Payment POST;
- bank/cash effect;
- allocation/settlement;
- reconciliation.

Purchasing event cannot directly mutate CASH/BANK.

## 8. External invoice import

Imported Supplier Invoice:
- staged/mapped;
- Supplier Party resolved;
- Product/service lines resolved where needed;
- duplicate external invoice identity checked;
- normal 2-way/3-way/direct policy applied;
- no bypass of match/tolerance/approval.

Retry must be idempotent.

## 9. Purchase Return

Physical return event:
- Inventory/Warehouse STOCK OUT.

Financial adjustment event:
- Finance payable correction.

Each side has independent idempotency and status.

## 10. Observability

Trace:
- company;
- document/version;
- source PO/GR/Invoice;
- event id;
- provider/account;
- attempt/status;
- redacted error;
- correlation id.

No secrets/sensitive supplier data in uncontrolled logs.

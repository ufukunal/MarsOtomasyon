# Returns / RMA Workflows

Status: FROZEN — PLAN-009

## 1. Separate progress dimensions
Do not collapse:
- authorization/case lifecycle;
- physical progress;
- QC/disposition;
- financial adjustment progress;
- refund progress.

A return can be physically complete and financially pending, or financially adjusted while physical processing remains independently controlled where policy permits.

## 2. Customer Return / RMA
Case lifecycle:
DRAFT → AUTHORIZED → AWAITING_RECEIPT → PARTIALLY_RECEIVED → RECEIVED → QC_PENDING → DISPOSITIONED → COMPLETED.
Optional: REJECTED, CANCELLED before posting; exception/reversal states remain linked history.

Authorization: no ledger effect.

Physical receipt:
AUTHORIZED/AWAITING_RECEIPT → POST RECEIPT
- validate source/company/Party/Product/UOM/lot/serial and remaining;
- Inventory STOCK IN;
- disposition QUARANTINE;
- physical processed increases;
- Account/Cash/Bank NONE.

QC:
QUARANTINE → AVAILABLE / QUALITY_HOLD / REWORK / DAMAGED.
SCRAP requires separate explicit STOCK OUT after eligible disposition decision.

Financial credit:
- may occur only against eligible approved financial basis;
- CUSTOMER_RECEIVABLE CREDIT;
- no STOCK;
- no Cash/Bank;
- financial progress changes independently.

Refund:
- only Finance against eligible customer credit/advance;
- CUSTOMER_RECEIVABLE DEBIT + Cash/Bank OUT;
- no STOCK.

## 3. Supplier / Purchase Return
Case lifecycle:
DRAFT → AUTHORIZED → PICKING/READY → PARTIALLY_SHIPPED → SHIPPED → FINANCIAL_PENDING/ADJUSTED → COMPLETED.
Pre-post cancellation allowed.

Physical shipment POST:
- validate Goods Receipt lineage or approved source-less exception;
- validate current physical custody/status/lot/serial;
- STOCK OUT;
- no Supplier Account effect;
- no Cash/Bank.

Financial adjustment:
- SUPPLIER_PAYABLE DEBIT;
- no STOCK;
- no Cash/Bank.

Supplier refund:
- against eligible supplier debit/advance position;
- SUPPLIER_PAYABLE CREDIT + Cash/Bank IN;
- no STOCK.

## 4. Source-linked eligibility
Customer physical eligible quantity is bounded by net eligible Sales physical source quantity, normally Dispatch lineage, less prior net returns.
Supplier physical eligible quantity is bounded by net eligible Goods Receipt quantity less prior net supplier returns.
Invoice/financial sources bound financial adjustment separately.
Order alone does not create physical eligibility.

## 5. Source-less exception
DRAFT → EXCEPTION_REVIEW → AUTHORIZED_EXCEPTION or REJECTED.
Requires permission + reason + evidence + approval.
Physical and financial processing then follow normal separate posting commands.
Source-less status never bypasses Product/Party/company/lot/serial/valuation controls.

## 6. Partial and over-return
Partial physical/financial processing creates separate movement/reference records.
Each command validates current remaining at POST.
Over eligible source quantity is blocked in normal path.
An approved exceptional excess is represented as exception/source-less scope, not by increasing source shipped/received quantity.

## 7. Reversal
Authorization cancellation has no ledger compensation.
Physical POST reversal creates linked Inventory compensation after dependency checks.
Financial credit/adjustment/refund reversal creates Finance compensation under period rules.
If returned stock was already released, moved, scrapped or used, simple receipt reversal is blocked until downstream physical dependency is compensated.
If a credit was already refunded, simple credit reversal is blocked until refund dependency is compensated.

## 8. Replacement/exchange
Return case may link a replacement Sales document.
Replacement follows normal Sales Reservation/Dispatch/Invoice effects.
No replacement stock-out or receivable is posted by Returns itself.

## 9. Effect matrix
| Action | STOCK | CUSTOMER | SUPPLIER | CASH/BANK | VALUATION |
|---|---|---|---|---|---|
| Customer RMA authorize | NONE | NONE | NONE | NONE | NONE |
| Customer return receipt POST | IN → QUARANTINE | NONE | NONE | NONE | Finance return valuation link |
| Customer financial credit | NONE | CREDIT | NONE | NONE | COGS/value correction as applicable |
| Customer refund | NONE | DEBIT | NONE | OUT | realized FX if applicable |
| Supplier return authorize | NONE | NONE | NONE | NONE | NONE |
| Supplier return shipment POST | OUT | NONE | NONE | NONE | purchase-return value OUT |
| Supplier financial adjustment | NONE | NONE | DEBIT | NONE | source-linked cost correction as applicable |
| Supplier refund | NONE | NONE | CREDIT | IN | realized FX if applicable |
| QC disposition only | status/location effect | NONE | NONE | NONE | normally NONE |

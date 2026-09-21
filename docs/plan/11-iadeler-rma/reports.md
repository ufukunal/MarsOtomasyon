# Returns / RMA Reporting Contract

Status: FROZEN PLAN-009 reporting semantics. Reports are not authority.

## 1. Pending RMA
Grain: case/line.
Show Party, source, Product, authorized quantity, physical processed/remaining, age, warehouse and exception state.

## 2. Customer received / QC
Show receipt date, quantity, lot/serial, warehouse/location, current disposition, QC pending age and release/rework/damage status.
Do not infer financial credit from physical receipt.

## 3. Financial pending
Show physical progress alongside customer credit/supplier adjustment progress, currency/value basis and Finance posting reference.
Do not infer Cash/Bank refund from Account adjustment.

## 4. Refund
Show Finance customer/supplier refund references, amounts/currency and reversal state.
Refund report derives from Finance transactions.

## 5. Supplier returns
Show Goods Receipt source, shipped/remaining quantity, lot/serial, financial adjustment progress and supplier refund state separately.

## 6. Partial / remaining
Original eligible source, authorized, net physically processed, physical remaining, financially adjusted and financial remaining are separate columns.
No generic return-complete total hides dimensions.

## 7. Source-less / exception
Show exception reason, evidence, approver, valuation basis, physical/financial status and age.
Over-source exceptions are never blended into normal source-linked quantity.

## 8. Disposition / loss
Show QUARANTINE, AVAILABLE, QUALITY_HOLD, REWORK, DAMAGED and subsequent SCRAP references.
Scrap/write-off value comes from Finance valuation.

## 9. Replacement
Show return case ↔ replacement Sales document links and independent statuses.
Replacement commercial progress is Sales-owned.

## 10. FX/value
Show original source currency/snapshot, correction transaction/base values and refund-date Finance FX references where applicable.
No report rewrites original commercial FX.

## 11. Security/export
Apply company/branch/warehouse/Finance permissions and masking.
Exports use same filters/security.

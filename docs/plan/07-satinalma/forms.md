# Purchasing UI / Form Contract

Status: FROZEN — PLAN-005

## 1. UX principles

- V38 list/detail structure remains product reference.
- Draft/Post/Reverse are visually distinct.
- source, processed and remaining quantities are visible.
- tolerance/match exceptions are explicit; no hidden auto-accept.
- posted documents are read-only except explicit correction/reversal actions.
- F2/keyboard-first ERP interaction remains supported.

## 2. Purchase Order list/detail

V38-aligned list:
- PO No
- Supplier
- Date
- Delivery
- Currency
- Order Amount
- Received
- Invoiced
- Receive Remaining
- Invoice Remaining
- State

Detail tabs:
- Lines
- Information
- Delivery
- Goods Receipt Progress
- Invoice Progress
- Tolerance
- Approval
- Files/Notes
- Timeline

Actions:
- Save
- Submit Approval when required
- Send Supplier
- Hold
- Create Goods Receipt
- Create Supplier Invoice
- Cancel Remainder
- Close

Tolerance panel must show configured maximum and actual exception use.

## 3. Goods Receipt

List:
- GR No
- Supplier
- PO
- Warehouse
- Date
- Qty
- Quarantine
- QCP/QC
- Disposition
- State

Line grid:
- Ordered
- Previously Received
- This Receipt
- Remaining
- Allowed Tolerance
- UOM/Base Qty
- Warehouse/Location
- Lot/Serial/Expiry

Primary action:
POST.

After POST:
- physical movement reference visible;
- initial status QUARANTINE for stockable line;
- Reverse, QC, Release, Return actions clearly separated.

## 4. Supplier Invoice

List:
- Invoice No
- Supplier
- Date/Due
- Currency
- Total
- Match status
- Payable effect
- Finance Payment context
- State

Detail:
- source PO/Receipt;
- 2-way/3-way/direct mode;
- ordered/received/already-invoiced/this-invoice/remaining;
- price variance;
- quantity variance;
- tax/discount/FX;
- approval;
- supplier balance read context from Finance;
- timeline/reversal.

Do not show editable authoritative supplier balance.

## 5. 3-Way Match workspace

Per line show side-by-side:
- PO qty/price;
- Receipt qty;
- already invoiced;
- Invoice qty/price;
- quantity variance;
- price variance;
- tolerance;
- status;
- exception reason;
- approver.

Actions:
- Match/Recalculate
- Submit Exception
- Approve/Reject Exception according to permission

A blocked line prevents normal Invoice POST.

## 6. Direct invoice UX

Direct/source-less mode is visibly labelled:
FINANCIAL-ONLY.

Allowed only for SERVICE/NON-STOCK.

Require:
- reason;
- permission;
- approval state.

UI must not offer warehouse/location/lot/serial stock posting controls for this mode.

## 7. Purchase Return

Show separately:
- physical return source GR;
- physical return quantity/status;
- source Supplier Invoice;
- financial adjustment status/value.

Actions:
- Ship Return
- Create Adjustment
- Complete when applicable sides are resolved.

No single checkbox pretends physical and financial return are the same effect.

## 8. Inactive/stale/error states

Handle explicitly:
- inactive Supplier;
- inactive/non-purchasable Product;
- stale PO version;
- over-receipt blocked;
- match exception;
- lot/serial missing;
- cross-company conflict;
- duplicate supplier invoice;
- provider/e-document error where later integrated.

Recoverable draft data should be preserved.

## 9. Mobile / receiving

Goods Receipt mobile path:
- scan-first Product/Variant/barcode;
- PO source;
- Warehouse/Location;
- quantity;
- lot/serial/expiry;
- clear quarantine status;
- offline/sync state when later implemented.

Desktop match/invoice remains dense-grid/keyboard focused.

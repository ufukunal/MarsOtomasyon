# Sales UI / Form Contract

## 1. UX principles

- V38 is the visual/product reference.
- Production UI is rebuilt in Mars.UI/TypeScript, not by copying chained V38 scripts.
- Lists are canonical operational homes; New actions live inside list/detail context instead of duplicating every New route in the sidebar.
- Status, source document, processed quantity and remaining quantity must be visible.
- Posted/finalized documents are read-only; correction uses explicit reversal/correction actions.
- F2/lookup and keyboard-first ERP behavior are preserved in future implementation.
- UI restrictions never replace server authorization.

## 2. V38 Sales screen mapping

| V38 route/concept | Decision | Reason / production contract |
|---|---|---|
| quote_list | KEEP | canonical Quote operational home; revision/status/date/value list is useful |
| quote_new | ADAPT | keep create workflow but launch from quote_list; remove sidebar duplicate |
| quote_detail | ADAPT | keep revision, requirement snapshot, approval/customer-review and convert-to-order concepts; state machine becomes explicit |
| sales_order_list | KEEP | canonical order home; reservation/sevk/faturalanan/kalan visibility is core |
| sales_order_new | ADAPT | keep creation from list; no duplicate sidebar route |
| sales_order_detail | ADAPT | keep hold/reservation/dispatch/invoice/cancel-remaining/close actions, but actions are permission/state driven |
| dispatch_list | KEEP | canonical shipment home with order/customer/warehouse/package/carrier/status |
| dispatch_new | ADAPT | creation preferably from eligible order context or list action; source relation required for normal Sales dispatch |
| dispatch_detail | KEEP/ADAPT | V38 v16.3 quantity layout strongly matches domain: order, previous shipped, this shipment, remaining; posted detail must be read-only |
| sales_invoice_list | KEEP/ADAPT | canonical invoice home; V14 removal of per-invoice Tahsilat/Kalan columns aligns with unresolved balance-only/allocation model |
| sales_invoice_new | ADAPT/BLOCKED | draft UI exists; source-less post behavior blocked by SALES-B002 |
| sales_invoice_detail | ADAPT | keep balance effect, e-document, correction, files/PDF/timeline; posted record read-only |
| proforma_list | KEEP | useful informational list |
| proforma standalone new | MERGE | V38 final UX says create Proforma from Quote or Sales Order source, not as an unrelated standalone document |
| proforma_detail | KEEP/ADAPT | read-only informational/source-linked document; no ledger effect |
| sales_returns | MERGE | V38 routes Sales Returns into returns_center; full return processing belongs Returns/RMA module |
| returns_center Sales tab | KEEP as future cross-module target | Sales module links to it but does not own full RMA behavior |
| sales_report | MERGE | V38 aliases to report_view:sales_summary / central report center |
| contact_detail Faturalar/Siparişler/Teklifler tabs | KEEP | cross-navigation/read view; New actions route to canonical list-first homes |
| product_detail Satışlar/En Çok Alan Cariler/Yıllık Performans | KEEP as read/report views | not authoritative transactional screens |
| dashboard sales/order widgets | ADAPT | retain operational navigation, but KPI formula/status source must come from Reporting contracts |

## 3. Quote list/detail

List:
- search/filter by quote number, customer, date, status;
- show revision, validity, total/currency, status;
- primary action: New Quote.

Detail:
- customer;
- quote date/validity;
- commercial lines;
- revision identifier/history;
- requirement/configuration snapshot where used;
- notes/files;
- approval/customer-send history;
- source/target links;
- actions based on state: save draft, submit internal review if policy applies, send customer, accept/record acceptance, new revision, convert to order, cancel.

Must not:
- reserve stock;
- show receivable/cash effect as if posted;
- overwrite externally reviewed prior revision.

Quote partial conversion UI is BLOCKED by SALES-B003.

## 4. Sales Order list/detail

List must expose:
- order number/customer/date/due date;
- total;
- reservation status;
- shipping progress;
- invoicing progress;
- remaining;
- business status.

Detail must expose line-level:
- ordered;
- reserved;
- shipped;
- invoiced;
- remaining-to-ship;
- remaining-to-invoice where relevant.

Actions:
- confirm/approval according to SALES-B007;
- hold/release;
- reserve according to SALES-B004;
- create dispatch;
- create invoice from eligible order quantity;
- cancel remaining;
- close when explicit completion criteria are met.

Do not hide the distinction between shipping-complete and invoicing-complete.

## 5. Reservation UI

Reservation is visible from Sales Order and Inventory/Warehouse views.

Show:
- source order/line;
- product/variant;
- warehouse;
- requested/active/consumed/released quantity;
- availability conflict;
- status;
- actor/time.

Do not present reservation as physical stock movement.

## 6. Dispatch UI

V38 v16.3 pattern is preferred:
- customer;
- source Sales Order;
- source warehouse;
- ship date;
- transport/carrier mode;
- package count;
- delivery/ambar information;
- line grid with Order / Previously Shipped / This Dispatch / Remaining / UOM.

Primary pre-post action:
- Sevkiyatı Kesinleştir.

Before POSTED:
- draft/picking fields may be edited subject to state and permissions.

After POSTED:
- line quantities are read-only;
- correction uses Düzelt / İptal or Reverse workflow;
- Kargoya/Ambara Teslim changes handoff status only and cannot post stock again.

## 7. Sales Invoice UI

List:
- invoice no, customer, date, due date, subtotal, tax, total, e-document, state.
- Do not add invoice paid/unpaid/open-amount columns until SALES-B001 is decided.

Detail:
- source references: dispatch/order/direct;
- customer legal/billing snapshot;
- lines, price, discount, tax, currency;
- balance effect;
- e-document;
- correction/reversal;
- files/PDF;
- timeline/audit.

POST action:
- Kesinleştir / Post.
- Once POSTED, financial fields are read-only.

Tahsilat action:
- opens/links Finance collection flow;
- does not mutate invoice directly;
- invoice allocation display remains BLOCKED until SALES-B001.

Direct new invoice:
- may be drafted from V38 list action;
- final posting behavior is blocked if physical stock semantics would be required and SALES-B002 is unresolved.

## 8. Proforma UI

- informational;
- generated from Quote or Sales Order;
- carries source reference;
- printable/PDF/customer communication;
- no stock/account/cash posting;
- conversion to Sales Invoice is allowed only as a source link; final invoice still follows Sales Invoice posting rules.

## 9. Sales return linkage UI

Sales-side views expose:
- source order/dispatch/invoice;
- return/RMA reference;
- product/quantity;
- physical receipt state;
- QC/disposition;
- financial credit/refund status.

Full actions live in Returns/RMA module. Sales should deep-link rather than duplicate the return engine.

## 10. Loading/error/empty/stale states

Every future async Sales screen must distinguish:
- loading;
- no data;
- no permission;
- filter returned zero rows;
- stale/concurrency conflict;
- provider/e-document pending/error;
- local posted state vs external send state.

On validation failure, preserve user-entered draft data where safe.

## 11. Keyboard/accessibility

Future Mars.UI implementation:
- F2 for customer/product lookups;
- predictable Tab/Enter flow;
- Escape for dialog close where safe;
- optional Ctrl+S only for draft save;
- visible focus;
- status text/icon plus color;
- dialogs with focus lifecycle;
- dense desktop grid; mobile uses task-focused views, not compressed desktop tables.

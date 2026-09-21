# Sales UI / Form Contract

Status: FROZEN — PLAN-002

## 1. UX principles

- V38 is product/visual reference; production UI is Mars.UI/TypeScript later.
- Lists remain canonical operational homes.
- State, exact source, original/processed/remaining quantities and amendment version are visible.
- Posted/finalized documents are read-only; correction uses explicit reversal/correction.
- UI restrictions never replace server authorization.
- Keyboard-first ERP behavior remains a requirement.

## 2. Quote UI

List:
- number, customer, revision, validity, total/currency, state, conversion progress.

Detail:
- exact revision;
- lines and remaining conversion quantity;
- commercial terms;
- approval/customer-send history;
- source-target Sales Orders.

Conversion:
- user can select eligible lines/quantities;
- repeated partial conversion is allowed;
- UI prevents cumulative conversion above offered quantity;
- state/progress shows PARTIALLY_CONVERTED until every line remaining is zero;
- then CONVERTED.

Approval:
- standard policy-compliant Quote proceeds without mandatory approval;
- policy exception/manual FX override shows PENDING_APPROVAL and exception reason;
- creator cannot approve own Quote.

## 3. Sales Order UI

List exposes:
- order/customer/date;
- effective version;
- reservation/shipping/invoicing progress;
- remaining-to-ship and remaining-to-invoice;
- approval/hold/amendment state.

Detail exposes per line:
- ordered effective quantity;
- reserved;
- shipped;
- invoiced;
- cancelled remainder;
- remaining-to-ship;
- remaining-to-invoice;
- source Quote revision/line;
- active amendment/version.

Actions:
- confirm / submit approval where required;
- manual Reserve;
- create Dispatch;
- create Invoice;
- controlled Amendment;
- cancel remainder;
- hold/release;
- close when no eligible remainder exists.

### Controlled Amendment UI

Do not edit confirmed history inline.

Amendment screen shows:
- current effective version;
- proposed delta;
- before/after quantity and commercial fields;
- processed floor (shipped/invoiced);
- active reservation impact;
- approval requirement/reason;
- mandatory amendment reason.

Rules surfaced in UI:
- decrease below processed floor blocked;
- excess Reservation must be released before activation;
- product/UOM change on processed line uses cancel eligible remainder + new line;
- commercial term change for processed scope is blocked; future scope uses new amendment line;
- quantity increase does not auto-reserve.

## 4. Reservation UI

Reservation action is explicit/manual.

Show:
- source Order/version/line;
- requested/active/consumed/released quantity;
- warehouse;
- availability/conflict;
- actor/time/state.

Do not present Reservation as physical movement.

## 5. Dispatch UI

Keep V38-style quantity grid:
Order / Previously Shipped / This Dispatch / Remaining / UOM.

Primary risk action:
`Sevkiyatı Kesinleştir / POST`.

After POST:
- line quantities read-only;
- correction uses Reverse;
- carrier handoff/delivery status does not post stock again.

## 6. Sales Invoice UI

List:
- invoice no, customer, date/due date, currency, net, tax, gross, e-document state, POST state.

Do not show authoritative:
- invoice paid/unpaid;
- invoice open amount;
- collection allocation status.

Customer account total/context may be shown when clearly labelled as current-account balance, not invoice settlement.

Detail:
- source Dispatch/Order/direct;
- immutable customer/product/tax/currency snapshot;
- KDV-exclusive line pricing;
- line/document discount allocation;
- line tax and totals;
- FX source/date/type/rate;
- balance effect;
- COGS recognition at POST;
- files/PDF/timeline;
- reversal.

Direct Invoice:
- may POST receivable and COGS;
- must display that STOCK effect is NONE;
- physical shipment requires Dispatch.

FX override:
- only actors with `sales.invoice.fx_override`;
- reason required;
- suggested source rate and override shown;
- override triggers approval;
- after POST values are immutable.

## 7. Calculation visibility

At draft/review show:
- unit price;
- line discount;
- allocated document discount;
- taxable base;
- KDV;
- line gross;
- currency;
- FX source/date/rate where relevant.

Posted totals are currency-minor-unit values and equal the sum of rounded lines. Hidden header balancing is forbidden.

## 8. Proforma and Return links

Proforma:
- sourced from Quote/Order;
- informational only;
- no ledger effect.

Sales Return link:
- show physical return state separately from financial credit/refund state;
- full actions remain Returns/RMA-owned.

## 9. Loading/error/stale states

Distinguish:
- loading;
- empty/filter-empty;
- no permission;
- stale version/concurrency conflict;
- reservation release conflict;
- provider/e-document pending/error;
- approval pending/rejected;
- local POSTED vs external send state.

User draft input is preserved on recoverable validation/conflict where safe.

# Sales Integration Contract

Status: FROZEN planning contract. Provider capabilities remain implementation-time verification items.

## 1. General rule

Reliable external effects follow:
business transaction → PostgreSQL state/outbox → Worker → provider adapter → delivery/result state.

Provider state never becomes authoritative Sales/Inventory/Finance truth.

## 2. Quote and approval events

Candidate internal/outbox events:
- QuoteApprovalRequested
- QuoteApproved
- QuoteCustomerSendRequested
- QuoteRevisionCustomerSendRequested
- QuotePartiallyConverted
- QuoteConverted

Rules:
- event references exact Quote revision;
- communication failure does not change Quote acceptance;
- approval binds exact revision and exception reasons.

## 3. Sales Order events

Candidate events:
- SalesOrderApprovalRequested
- SalesOrderApproved
- SalesOrderConfirmed
- SalesOrderHeld
- SalesOrderAmendmentSubmitted
- SalesOrderAmendmentActivated
- SalesOrderRemainderCancelled
- SalesOrderCompleted

Reservation:
- Order confirmation does not automatically request Reservation.
- manual Reservation is an explicit command/integration boundary with Inventory.
- amendment quantity decrease may require explicit Reservation release before activation.
- quantity increase does not auto-reserve.

## 4. Dispatch

Candidate:
- DispatchPosted
- DispatchHandedOver
- DispatchReversed

Rules:
- provider timeout/retry cannot create second Dispatch posting;
- carrier/tracking state cannot post stock;
- only local authoritative Dispatch POST changes physical inventory.

## 5. Sales Invoice / e-document

Candidate:
- SalesInvoicePosted
- SalesInvoiceReversed
- EDocumentSendRequested

Posted event/payload must use immutable calculation snapshot:
- KDV-exclusive prices;
- discounts;
- rounded line/tax totals;
- currency;
- FX source/date/rate;
- source document/version.

Provider failure after local POST:
- Invoice remains POSTED;
- ACCOUNT/COGS are not repeated;
- integration enters retry/error state.

Direct Invoice:
- STOCK effect is always NONE.

## 6. FX source

Default Sales Invoice rate source is TCMB döviz alış for invoice/tax-event date, with latest prior published business day fallback when no rate is published for that date.

Manual override:
- permission + reason + audit;
- B007 approval exception;
- source rate and override both preserved.

Provider or external FX services must not silently replace the accepted project rate policy.

## 7. Collection

Collection integration belongs Finance.

Frozen Sales behavior:
- customer balance context only;
- no Invoice allocation/open-item event;
- no Invoice paid/open-state event derived from Collection.

## 8. Returns

Physical and financial return events remain separate.

Candidate references:
- SalesReturnRequested
- ReturnReceived
- ReturnCreditPosted

Full ownership is frozen in Returns/RMA planning.

## 9. Commerce ingest

external order → staging/mapping → Mars Sales Order → normal Mars flow.

Requirements:
- external logical identity duplicate-safe;
- no provider-specific stock/accounting engine;
- imported Order still obeys approval, manual Reservation, Dispatch stock-out and Invoice financial rules.

## 10. Observability

Trace:
- correlation/event id;
- company;
- document/version;
- provider/account where relevant;
- attempt/status;
- external id where safe;
- retry count;
- redacted error metadata.

Secrets and sensitive PII are not logged.

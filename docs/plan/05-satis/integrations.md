# Sales Integration Contract

Status: planning only. No provider capability is assumed unless verified later.

## 1. General rule

External effects do not determine the local authoritative transaction outcome.

Flow where reliability matters:

Sales/Application transaction
→ PostgreSQL business state
→ Outbox in same transaction
→ Worker
→ provider adapter
→ delivery/result state
→ retry/reconciliation/error visibility

Provider-specific code does not own Sales business rules.

## 2. Quote communication

Potential triggers:
- Quote sent to customer
- New revision sent
- expiry/reminder communication if later approved

Outbox candidate events:
- QuoteCustomerSendRequested
- QuoteRevisionCustomerSendRequested

Requirements:
- stable Quote revision reference;
- channel/template reference;
- recipient resolved through authorized Communications contract;
- duplicate-safe consumption;
- delivery failure visible separately from Quote business state.

A failed email/WhatsApp does not mutate Quote to a false accepted/rejected state.

## 3. Sales Order downstream events

Potential internal/integration events:
- SalesOrderConfirmed
- SalesOrderHeld
- SalesOrderRemainderCancelled
- SalesOrderCompleted

Consumers may include:
- Inventory reservation workflow
- Warehouse work queue
- Commerce channel adapter
- notifications

Whether confirmation automatically requests reservation is SALES-B004.

## 4. Dispatch integrations

Potential events:
- DispatchPosted
- DispatchHandedOver
- DispatchReversed

Possible consumers:
- carrier/tracking adapter
- customer notification
- Commerce channel shipment update
- internal realtime/work queue

Rules:
- provider timeout cannot cause a second dispatch posting;
- provider retry is idempotent;
- carrier handoff update cannot post stock again;
- tracking/provider state is not authoritative stock truth.

Exact carrier capabilities are verified when provider implementation begins.

## 5. Sales Invoice / e-document

Potential events:
- SalesInvoicePosted
- EDocumentSendRequested
- SalesInvoiceReversed / correction event according to legal integration contract

Rules:
- local POSTED financial state and provider e-document send state are separate.
- if provider send fails after invoice commit, invoice remains locally posted and integration enters retry/error state.
- provider retry must not repost account ledger.
- invoice payload uses historical snapshot, not current mutable customer/product master.
- legal cancellation/reversal provider semantics must be verified against current provider documentation before implementation.

Exact e-Fatura/e-Arşiv provider is UNKNOWN.

## 6. Collection

Collection provider effects, if any, belong Finance/Payment integrations.

Sales only provides contextual link/deep-link.

SALES-B001 must be decided before any invoice-allocation event contract is created.

## 7. Return linkage

Sales may emit/consume references such as:
- SalesReturnRequested
- ReturnReceived
- ReturnCreditPosted

Full event ownership and semantics are frozen in Returns/RMA planning.

Physical and financial return events remain separate.

## 8. Commerce channel order ingestion

Commerce Core contract from existing project rules:

external order
→ staging/mapping
→ Mars Sales Order
→ normal reservation/dispatch/invoice/collection flow.

Requirements:
- external channel/account + external_order_id unique logical identity;
- duplicate ingest cannot create a second Mars Sales Order;
- raw provider state and normalized Mars state are distinguishable;
- provider-specific accounting/stock engines are forbidden.

Exact provider APIs are outside PLAN-002.

## 9. Reconciliation

Any integration relying on webhook delivery should also define later reconciliation/polling where provider characteristics require it.

No blind retry:
- timeout/5xx/429 may be retryable by provider policy;
- validation/auth/business errors are classified separately.

## 10. Observability

Every integration attempt should be traceable by:
- correlation/event id
- company
- Sales entity/document reference
- provider/account where relevant
- direction
- attempt
- status
- provider request/external id where safe
- retry count
- timestamps
- redacted error metadata.

Secrets/payload PII must not leak to logs.

## 11. Integration decision gates

- exact communication providers
- e-document provider/capabilities
- carrier providers
- Commerce provider capabilities
- webhook verification mechanism per provider
- retry/reconciliation intervals
- retention/redaction policy for provider payloads

These are intentionally deferred until their implementation/module plan.

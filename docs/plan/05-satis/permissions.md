# Sales Permissions and Scope

Status: FROZEN planning-level permission contract.

## 1. Principles

- Authorization is server-side; UI visibility is not security.
- Every state-changing command validates actor, company and relevant branch/warehouse scope.
- Read permission does not imply post/reverse/approve/amend permission.
- Approval is exception-based under B007.
- SoD invariant: creator != approver for every approval-required Quote, Sales Order or Order Amendment.

## 2. Permission namespace

Quote:
- sales.quote.read
- sales.quote.create
- sales.quote.edit_draft
- sales.quote.revise
- sales.quote.submit_approval
- sales.quote.approve
- sales.quote.send_customer
- sales.quote.convert_order
- sales.quote.cancel
- sales.quote.export

Sales Order:
- sales.order.read
- sales.order.create
- sales.order.edit_draft
- sales.order.submit_approval
- sales.order.approve
- sales.order.confirm
- sales.order.amend
- sales.order.activate_amendment
- sales.order.hold
- sales.order.release_hold
- sales.order.cancel_remaining
- sales.order.close
- sales.order.export

Reservation:
- authoritative permission namespace belongs Inventory/Warehouse.
- Sales requires equivalent create/release permissions before exposing manual Reservation actions.

Dispatch:
- sales.dispatch.read
- sales.dispatch.create
- sales.dispatch.edit_draft
- sales.dispatch.pick
- sales.dispatch.pack
- sales.dispatch.post
- sales.dispatch.handoff
- sales.dispatch.reverse
- sales.dispatch.export

Sales Invoice:
- sales.invoice.read
- sales.invoice.create
- sales.invoice.edit_draft
- sales.invoice.post
- sales.invoice.reverse
- sales.invoice.fx_override
- sales.invoice.send_edocument
- sales.invoice.export

Collection remains Finance-owned:
- finance.collection.read/create/post/reverse.

## 3. Conditional approval policy

Policy owner/source:
- company-scoped Sales Commercial Policy in Settings/configuration.
- numeric/tolerance values are configuration; they are not hard-coded in Sales.
- if an applicable policy record is absent, any manual commercial deviation is treated as approval-required.

Quote approval is required when:
- unit price is manually overridden outside active policy;
- line/document discount is manually overridden outside active policy tolerance;
- payment terms are outside active policy;
- manual FX override is used.

Sales Order approval is required when:
- price/discount/currency/payment terms deviate from accepted Quote;
- a direct/source-less Order contains a commercial-policy exception;
- manual FX override is used;
- a controlled post-confirmation amendment increases commercial exposure or changes commercial terms.

No mandatory approval is required for:
- policy-compliant standard Quote;
- unchanged Sales Order generated from accepted Quote;
- operational amendment that changes only non-commercial notes/contact/delivery metadata and does not increase exposure, subject to normal amend permission.

SoD:
- creator cannot approve own document/amendment;
- approver must hold explicit approve permission;
- approval binds exact revision/amendment;
- material change invalidates/re-evaluates approval.

## 4. FX override control

Manual FX override:
- requires `sales.invoice.fx_override`;
- mandatory reason;
- suggested source/date/rate and overridden rate are audited;
- triggers B007 approval;
- after Invoice POST it is immutable.

## 5. Controlled amendment permissions

`sales.order.amend`:
- create amendment draft only.

`sales.order.activate_amendment`:
- activate when validation/reservation release and approval requirements are satisfied.

Amendment cannot bypass:
- processed quantity floor;
- Reservation release requirement;
- stale-version conflict;
- creator/approver SoD when approval required.

## 6. High-risk commands

Distinct permissions required for:
- approval;
- controlled amendment activation;
- Dispatch POST/reverse;
- Invoice POST/reverse;
- FX override;
- Collection POST/reverse;
- financial credit/refund.

## 7. Audit

Privileged transitions record:
- actor;
- company/branch/warehouse context;
- action;
- document/version;
- prior/new state;
- reason;
- source-target references;
- approval policy/reason where relevant;
- timestamp/correlation id.

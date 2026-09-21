# Purchasing Permissions and Scope

Status: FROZEN planning-level permission contract.

## 1. Principles

- server-side authorization;
- company scope mandatory;
- Warehouse scope required for Goods Receipt physical actions;
- read/create does not imply approve/post/reverse;
- match exception and direct invoice are high-risk actions;
- UI hiding is not security.

## 2. Permission namespace

Purchase Order:
- purchasing.order.read
- purchasing.order.create
- purchasing.order.edit_draft
- purchasing.order.submit_approval
- purchasing.order.approve
- purchasing.order.send
- purchasing.order.amend
- purchasing.order.hold
- purchasing.order.cancel_remaining
- purchasing.order.close
- purchasing.order.export

Goods Receipt:
- purchasing.receipt.read
- purchasing.receipt.create
- purchasing.receipt.edit_draft
- purchasing.receipt.post
- purchasing.receipt.reverse
- purchasing.receipt.release_request

Supplier Invoice:
- purchasing.invoice.read
- purchasing.invoice.create
- purchasing.invoice.edit_draft
- purchasing.invoice.post
- purchasing.invoice.reverse
- purchasing.invoice.direct_create
- purchasing.invoice.fx_override
- purchasing.invoice.export

Match:
- purchasing.match.read
- purchasing.match.recalculate
- purchasing.match.submit_exception
- purchasing.match.approve_exception

Purchase Return:
- purchasing.return.read
- purchasing.return.create
- purchasing.return.post_physical
- purchasing.return.request_financial_adjustment

Payment remains Finance-owned.

## 3. Purchase Order approval

Conditional approval is triggered by:
- Purchasing Policy commercial exception;
- non-zero tolerance request;
- material post-approval amendment;
- other explicitly configured policy condition.

SoD:
creator != approver for approval-required PO/amendment.

Approval binds exact version/snapshot.

## 4. Match exception approval

Any accepted:
- over-receipt;
- over-invoice;
- non-zero price variance;
- direct/source-less invoice

requires exact exception evidence.

Approver:
- must hold match/appropriate approval permission;
- cannot be creator of the exception document when SoD applies.

A changed Invoice/PO/Receipt invalidates stale approval.

## 5. Direct Supplier Invoice

Requires:
- `purchasing.invoice.direct_create`;
- SERVICE/NON-STOCK only;
- reason;
- approval;
- same-company active Supplier.

Permission cannot bypass STOCKABLE receipt requirement.

## 6. Goods Receipt

POST requires:
- receipt.post;
- authorized Warehouse scope;
- valid Product tracking requirements;
- PO/tolerance validation.

Receipt permission does not grant:
- Quality pass;
- inventory count adjustment;
- Finance payable post.

## 7. Reversal

Separate permission required.

Receipt reverse:
- physical dependent-state checks;
- compensating inventory effect.

Invoice reverse:
- financial dependent-state checks;
- Finance account reversal;
- no stock effect.

## 8. Sensitive Finance projections

Purchasing screens may display supplier balance/payment context only if actor holds relevant Finance read permission.

Purchasing permission cannot edit balance, payment or settlement.

## 9. Audit

Record:
- actor/company/warehouse;
- document/version/action;
- prior/new state;
- tolerance/variance;
- approval/reason;
- source-target quantities;
- reversal relation;
- correlation/time.

Audit is not inventory/account ledger.

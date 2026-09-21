# Finance / Treasury Permissions and SoD

Status: FROZEN planning-level authorization contract.

## 1. Principles

- all financial authorization is server-side;
- company scope is mandatory;
- branch scope is enforced for Cash/Bank where configured;
- create/edit draft does not imply POST;
- POST does not imply Reverse;
- high-risk manual valuation, period override, role netting and reconciliation exceptions require distinct permissions;
- UI visibility is not authorization.

## 2. Permission namespace

Account / Party Finance:
- finance.account.read
- finance.statement.read
- finance.risk.read
- finance.risk.manage
- finance.hold.manage
- finance.role_netting.create
- finance.role_netting.approve
- finance.role_netting.reverse

Collection:
- finance.collection.read
- finance.collection.create
- finance.collection.edit_draft
- finance.collection.post
- finance.collection.reverse
- finance.collection.advance

Payment:
- finance.payment.read
- finance.payment.create
- finance.payment.edit_draft
- finance.payment.submit_approval
- finance.payment.approve
- finance.payment.post
- finance.payment.reverse
- finance.payment.advance

Refund:
- finance.refund.read
- finance.refund.create
- finance.refund.approve
- finance.refund.post
- finance.refund.reverse

Cash:
- finance.cash.read
- finance.cash.manage_account
- finance.cash.post
- finance.cash.reverse
- finance.cash.count
- finance.cash.count_review
- finance.cash.count_approve
- finance.cash.adjust

Bank:
- finance.bank.read
- finance.bank.manage_account
- finance.bank.post
- finance.bank.reverse
- finance.bank.statement_import
- finance.bank.reconcile
- finance.bank.reconcile_exception

Transfer / FX:
- finance.transfer.create
- finance.transfer.approve
- finance.transfer.post
- finance.transfer.reverse
- finance.fx.override
- finance.fx.revalue
- finance.fx.revalue_approve

Valuation:
- finance.cost.read
- finance.cost.late_adjust
- finance.cost.manual_valuation
- finance.cost.manual_valuation_approve
- finance.cost.reconcile
- finance.cost.reverse

Period:
- finance.period.read
- finance.period.freeze
- finance.period.close
- finance.period.override
- finance.period.reopen

Export:
- finance.export

## 3. Company / branch scope

Account Ledger:
- company mandatory;
- Party must belong same company.

Cash/Bank:
- company mandatory;
- branch scope follows account ownership/access.

Cross-company:
- no direct Collection/Payment/role-netting/treasury transfer in PLAN-007.
- intercompany finance is a future explicit workflow, not a scope bypass.

## 4. Collection

Ordinary Collection POST:
- collection.post;
- Party/customer-role access;
- target Cash/Bank access.

Customer Advance portion:
- finance.collection.advance;
- explicit classification/reason;
- approval when Finance Policy requires.

No Collection permission grants Invoice allocation because that authority does not exist.

## 5. Payment SoD

Payment is high-risk.

Normal rule:
- creator cannot approve own approval-required Payment;
- approval binds exact amount/currency/source account/Party/posting date/version;
- edit after approval invalidates approval.

Supplier Advance:
- explicit finance.payment.advance;
- reason;
- approval.

Payment POST validates current Cash/Bank funds/policy again.

## 6. Refund

Refund requires:
- eligible entitlement/credit;
- refund.post;
- Cash/Bank account access.

Approval is mandatory when:
- Finance Refund Policy threshold/condition requires;
- manual eligibility exception would otherwise be needed.

There is no generic permission to refund beyond eligible cap.

## 7. Role netting

Always:
- explicit permission;
- reason;
- approval;
- creator != approver.

Cannot cross:
- company;
- Party;
- currency.

Cannot exceed eligible balances.

## 8. Cash

Cash adjustment from count:
- counter cannot approve own non-zero discrepancy;
- count approval distinct from cash transaction entry.

Cash negative balance cannot be overridden by ordinary/admin cash permission.

## 9. Bank overdraft

Only active Bank Policy may allow negative book balance.

Permissions cannot invent overdraft.
Changing overdraft policy is Finance configuration governance, audited separately.

## 10. Treasury / FX transfer

POST requires:
- source account access;
- target account access;
- transfer.post.

Approval required by company Treasury Policy and always when:
- FX manual override;
- unusual fee/amount mismatch;
- configured amount/risk threshold.

FX override:
- finance.fx.override;
- reason;
- suggested/reference vs actual values;
- approval.

## 11. Statement import / reconciliation

Import permission:
- can load statement evidence;
- cannot create Bank Ledger automatically.

Reconcile:
- may link eligible evidence to eligible posted movement.

Create-from-statement:
- requires the permission of the resulting Finance transaction;
- statement import permission alone is insufficient.

Ignore statement:
- reconciliation exception permission + mandatory reason.

## 12. Financial reversal

Reverse is distinct permission by transaction family.

Reversal requires:
- reason;
- original posted transaction;
- current dependency/reconciliation check;
- open/frozen period rules.

Admin role does not imply silent edit/delete.

## 13. Period control

Freeze/Close:
- high-risk Finance/Settings permission;
- audit.

FROZEN override:
- finance.period.override;
- reason;
- approval;
- exact transaction logged.

CLOSED reopen:
- finance.period.reopen;
- separate approval;
- creator/reopener cannot self-approve where SoD configured;
- ordinary POST permission cannot reopen.

## 14. Manual valuation

Positive Count Adjustment without valid moving-average basis:
- finance.cost.manual_valuation;
- explicit unit value/source/reason;
- finance.cost.manual_valuation_approve by separate actor.

Zero-cost default is forbidden.

Late-cost/revaluation operations have distinct permissions and retain source evidence.

## 15. Risk / hold

Finance owns:
- credit-limit edit;
- manual hold/release;
- risk-policy configuration.

Sales may read/evaluate signal but cannot grant itself Finance risk permission.

Manual hold release is audited and may require approval by policy.

## 16. Sensitive data

Bank Account/IBAN:
- masked where full value is unnecessary;
- exports obey finance permission.

Statement raw evidence:
- restricted to authorized Finance roles;
- retention/download permission distinct when implemented.

Logs/audit never expose secrets, credentials or unnecessary bank-sensitive payloads.

## 17. Audit

Record for high-risk action:
- actor;
- company/branch;
- transaction/source;
- amount/currency/base value;
- account(s)/Party role;
- before/after derived balance preview where useful;
- reason;
- approval;
- FX/policy snapshot;
- posting period;
- correlation/idempotency identity;
- original/reversal relation.

Audit is not Account/Cash/Bank Ledger.

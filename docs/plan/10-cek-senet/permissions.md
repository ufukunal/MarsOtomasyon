# Checks / Promissory Notes Permissions and SoD

Status: FROZEN planning contract.

## 1. Permission families
- instruments.read
- instruments.create
- instruments.register_post
- instruments.receive
- instruments.issue
- instruments.deliver
- instruments.endorse
- instruments.bank_handoff
- instruments.collect
- instruments.pay
- instruments.bounce
- instruments.protest
- instruments.return
- instruments.reverse
- instruments.approve
- instruments.export

Finance ledger effects additionally require the applicable Finance posting/account access contract.

## 2. Scope
Every command enforces company.
Branch/custody and Bank/Cash account access are checked where applicable.
Cross-company endorsement/handoff/source link is forbidden.

## 3. Custody
Possessing read/create permission does not grant custody transition.
Receive, endorse, bank handoff and return are distinct permissions.
Physical/digital evidence download follows file authorization when implemented.

## 4. SoD
High-risk endorsement, outgoing delivery/payment, exceptional bounce/protest compensation and reversal evaluate approval policy.
When approval is required:
- creator/initiator cannot approve own action;
- approval binds exact instrument/version/action/amount/currency/target;
- material edit invalidates approval.

## 5. Settlement
Collection/payment permission cannot bypass Finance posting period, Cash/Bank policy or idempotency.
Bank handoff permission cannot post Bank money.

## 6. Reversal
Reverse is separate permission, requires reason and dependent-movement check.
Admin/UI access never means silent edit/delete.

## 7. Audit
Record actor, company/branch, instrument, action, amount/currency, custody before/after, financial references, target Party/Bank, reason, approval, correlation/idempotency and original/reversal lineage.

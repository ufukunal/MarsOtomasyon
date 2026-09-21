# PLAN-008 Acceptance Criteria

Status: COMPLETED / FROZEN.

- [x] Incoming CHECK lifecycle deterministic.
- [x] Outgoing CHECK lifecycle deterministic.
- [x] Incoming PROMISSORY_NOTE lifecycle deterministic.
- [x] Outgoing PROMISSORY_NOTE lifecycle deterministic.
- [x] Custody and financial state are explicitly separate.
- [x] Incoming receipt recognition: CUSTOMER CREDIT + instrument receivable; no Cash/Bank.
- [x] Outgoing delivery recognition: SUPPLIER DEBIT + instrument payable; no Cash/Bank.
- [x] Actual collection/payment is the Cash/Bank recognition point.
- [x] Endorsement is whole eligible remaining amount only in core; Supplier debit and no Cash/Bank.
- [x] Bank handoff is custody-only.
- [x] Partial collection/payment rules and cumulative cap are deterministic.
- [x] Bounce/unpaid/protest/return compensation is explicit and append-oriented.
- [x] Reversal retains original history and checks dependencies.
- [x] Original nominal amount/currency and accepted snapshots are immutable.
- [x] FX boundary follows PLAN-007 Finance carrying/realized FX principles.
- [x] No second Account/Cash/Bank authority exists.
- [x] No Invoice allocation/open-item authority exists.
- [x] No Customer/Supplier automatic cross-role netting exists.
- [x] Permissions/SoD/company scope are explicit.
- [x] Duplicate registration/settlement and concurrent transition risks are defined for P3.
- [x] Reports derive from instrument authority + Finance ledgers.
- [x] No physical STOCK effect exists.
- [x] No material instrument business blocker remains.
- [x] No SQL/application implementation was added.
- [x] Heavy tests were not run; Full Test Day backlog recorded.

Completion planning metrics:
- Master: 8 / 30 = 26.7%
- P2 Core Commercial: 7 / 8 = 87.5%

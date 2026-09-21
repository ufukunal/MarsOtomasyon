# PLAN-003 Acceptance Criteria

Status: COMPLETED / FROZEN — planning only.

## 1. Ownership and identity

- [x] One Party is the authoritative company-scoped live counterparty identity.
- [x] PERSON / ORGANIZATION distinction is explicit.
- [x] CUSTOMER and SUPPLIER are roles, not duplicate master identities.
- [x] One Party can hold both CUSTOMER and SUPPLIER roles.
- [x] Party Code ownership and role-code boundary are defined.
- [x] company scope is explicit; silent cross-company sharing is forbidden.

## 2. Legal identity / contact / address

- [x] legal vs display/trade identity semantics are defined.
- [x] tax identity conceptual model is defined.
- [x] Turkey VKN/TCKN structural schemes are recorded.
- [x] e-document identity/address requirement boundary is explicit.
- [x] contacts and communication points have lifecycle/ownership.
- [x] billing/shipping/general address semantics and lifecycle are defined.
- [x] live master vs historical document snapshot is deterministic.

## 3. Lifecycle and duplicate control

- [x] Party ACTIVE / INACTIVE / MERGED lifecycle is defined.
- [x] role ACTIVE / INACTIVE lifecycle is independent.
- [x] deterministic identity collision vs fuzzy duplicate warning is separated.
- [x] no fuzzy automatic merge.
- [x] logical merge survivor/source lineage is defined.
- [x] merge cannot erase historical document snapshots.
- [x] inactive/merged Party future-selection behavior is defined.
- [x] stale/concurrent edit/merge risks are recorded for P3.

## 4. Finance/commercial boundaries

- [x] customer/supplier balance is Finance-ledger-derived.
- [x] Party master has no authoritative current balance.
- [x] credit/risk/hold ownership is Finance.
- [x] no automatic customer/supplier receivable-payable netting is introduced.
- [x] currency/payment-term defaults are optional policy references, not posted financial truth.
- [x] frozen Sales balance-only Collection semantics remain valid.
- [x] posted Sales customer snapshots remain immutable.

## 5. Permissions / UI / integration / reports

- [x] planning-level Party permission namespace exists.
- [x] sensitive tax identity read/export boundary exists.
- [x] merge/deactivate/reactivate are distinct high-risk permissions/actions.
- [x] list/detail/create/edit/duplicate/merge UX contract exists.
- [x] Party master-data event/outbox boundaries exist.
- [x] external IDs are mappings, not canonical identity.
- [x] reporting/read projections are non-authoritative.
- [x] Full Test Day backlog includes duplicate, merge, cross-company, snapshot and dual-role risks.

## 6. Explicitly delegated non-blocking details

The following are not material PLAN-003 blockers because ownership is deterministic and Party core does not depend on their exact values:

- exact Party Code string/series format → Settings/Numbering.
- exact VKN/TCKN checksum/enrollment/provider validation → current official integration/legal rules.
- credit-limit/risk scoring/hold transitions → Finance planning.
- receivable/payable netting/offset → Finance planning.
- exact payment-term/currency catalog and precedence → Settings + Sales/Purchasing.
- CRM files/notes/tags → later source-backed module contracts.

## 7. Fast verification checklist

Before PLAN-003 handoff:
- all ten Party planning files exist and are non-empty;
- no empty placeholder remains;
- CUSTOMER/SUPPLIER are roles of Party;
- current balance is nowhere made Party authority;
- live master edits cannot mutate historical Sales snapshots;
- duplicate fuzzy matches do not auto-merge;
- merge is logical/audited and preserves history;
- company scope is explicit;
- no SQL/migration/C#/TypeScript implementation was added;
- final main HEAD is reverified.

## 8. Exit condition

PLAN-003 Party / Customer / Supplier planning is frozen.

Next repository-defined work package:
PLAN-004 — Product / Inventory Master model.

PLAN-004 content must start only in a separate task/session after state/handoff update.

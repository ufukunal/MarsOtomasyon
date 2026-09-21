# Current Handoff

## Repository

- Repository: ufukunal/MarsOtomasyon
- Allowed branch: main
- Current HEAD: verify from Git at session start
- Master plan: docs/plan/master-project-plan.md
- Session protocol: docs/ai/session-execution-protocol.md

## Current phase

P2 — Core Commercial Workflow Planning

## Planning progress

Counting basis:
- master-project-plan section 8;
- only COMPLETED / FROZEN work packages count.

Current:
- Master planning sequence: 7 / 30 = 23.3%
- P2 core commercial planning: 6 / 8 = 75.0%

Long planning sessions and final SESSION REPORT must include these metrics.

## Completed predecessor

PLAN-007 — Finance / Treasury workflow contract

Status: COMPLETED / FROZEN

Primary completion evidence:
- `55079aeda8b7345b392f55c8bc13d366b3a32a0c` — PLAN-007 acceptance criteria completed.

Finance planning contracts:
- docs/plan/09-finans-kasa-banka/README.md
- docs/plan/09-finans-kasa-banka/plan.md
- docs/plan/09-finans-kasa-banka/workflows.md
- docs/plan/09-finans-kasa-banka/forms.md
- docs/plan/09-finans-kasa-banka/data-contract.md
- docs/plan/09-finans-kasa-banka/permissions.md
- docs/plan/09-finans-kasa-banka/integrations.md
- docs/plan/09-finans-kasa-banka/reports.md
- docs/plan/09-finans-kasa-banka/acceptance-criteria.md
- docs/plan/09-finans-kasa-banka/full-test-day.md

No SQL schema/migration, application code, deployment or heavy tests were created/run by PLAN-007.

## Frozen Finance / Treasury decisions

- Account Ledger is authoritative Party financial truth.
- CUSTOMER_RECEIVABLE and SUPPLIER_PAYABLE are separate financial roles.
- CUSTOMER DEBIT increases receivable; CUSTOMER CREDIT decreases it / creates customer credit.
- SUPPLIER CREDIT increases payable; SUPPLIER DEBIT decreases it / creates supplier advance.
- Customer and Supplier role balances never auto-net.
- explicit same-Party role netting requires same company/Party/currency, eligible balances, reason, approval and creator != approver.
- Collection is balance-only CUSTOMER CREDIT + Cash/Bank IN.
- Supplier Payment is balance-only SUPPLIER DEBIT + Cash/Bank OUT.
- no authoritative Invoice allocation, Invoice paid/open balance or open-item settlement state exists.
- customer/supplier advances are explicit opposite-sign role positions rather than invoice allocations.
- Cash Ledger and Bank Ledger are authoritative money truth; mutable current-balance fields are not authority.
- Cash normal negative balance is blocked.
- Bank negative book balance requires explicit account overdraft policy; absent policy is blocked.
- same-currency Treasury transfer is paired source OUT + target IN atomically; fees are explicit.
- FX Transfer freezes exact source/target amounts, actual executed rate/base values and fees.
- commercial document FX remains the frozen TCMB default; actual treasury conversion rate is the FX Transfer transaction authority.
- realized FX uses weighted carrying base of the foreign-currency monetary position and does not require Invoice allocation.
- unrealized FX/revaluation is a separate period-end process under versioned Finance Revaluation Policy.
- Bank statement import is evidence/staging only and never posts Bank Ledger.
- reconciliation links statement evidence to posted Bank movements; suggestions are non-authoritative and partial/many-to-many matched amounts cannot overmatch.
- Account/Cash/Bank posted history uses reversal/compensation.
- Finance Posting Period states are OPEN / FROZEN / CLOSED.
- perpetual moving weighted average is the frozen inventory valuation method for current core planning.
- valuation pool is company + Product/Variant + Base UOM + company base currency; Warehouse transfers do not revalue.
- Goods Receipt introduces provisional inventory value without payable.
- Sales Dispatch removes carrying value and creates dispatched-not-invoiced cost bridge.
- Sales Invoice recognizes COGS from the bridge and cannot reduce inventory value again.
- Supplier Invoice / landed-cost late delta is source-linked and split across on-hand valuation, dispatch bridge and recognized COGS as applicable.
- positive Count Adjustment with no valid moving average requires explicit approved unit valuation; silent zero-cost inventory is forbidden.
- physical scrap remains Warehouse-owned; Finance owns carrying-value write-off.
- Finance owns customer credit/risk/hold; Sales consumes the signal.

## V38 migration outcome

KEEP / ADAPT:
- Balance List / Detailed Party Statement
- Collection / Payment / Refund
- Cash Accounts / Cash Movements / Cash Count
- Advances
- Bank Accounts / Bank Movements
- Virman / FX Transfer
- Statement Import
- Bank Reconciliation
- Risk / Credit Limits
- Inventory Cost

SUPERSEDED AS AUTHORITY:
- V38 Open Items invoice-settlement model
- V38 settlement workspace invoice allocation

Reason:
frozen Sales B001 has higher authority and mandates balance-only Collection with no Invoice allocation/open-item truth.

No external web research was required for PLAN-007.

## Repository dependency correction

Repository directory tree confirms:
- Checks / Promissory Notes canonical target: `docs/plan/10-cek-senet/`
- Returns / RMA canonical target: `docs/plan/11-iadeler-rma/`

Master P2 dependency order requires:
Finance
→ Checks / Promissory Notes
→ Returns / RMA
→ then logical database phase may begin.

The older compact backlog entry that placed Logical DB immediately after PLAN-007 is not dependency-safe and is corrected by current state/task records.

## Next safe work package

PLAN-008 — Checks / Promissory Notes workflow contract

Target:
`docs/plan/10-cek-senet/`

PLAN-008 is READY but content work has not started.

Before work:
- verify real main HEAD;
- read governance/master/state/handoff;
- read frozen Party, Sales and Finance contracts in particular;
- inspect current 10-cek-senet files;
- route Accounting/Finance + ERP skills and reviewers;
- use V38 incoming/outgoing checks/notes screens and instrument detail as product reference;
- preserve balance-only account settlement and avoid duplicate Cash/Bank effects;
- report master/P2 percentages during work;
- produce CONTEXT RECEIPT.

After PLAN-008, PLAN-009 Returns/RMA at `docs/plan/11-iadeler-rma/` remains required before logical DB planning.

Do not start Returns, logical SQL schema, application code or Full Test Day in PLAN-008.

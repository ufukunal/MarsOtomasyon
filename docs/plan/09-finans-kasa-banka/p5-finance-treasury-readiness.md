# FINANCE-IMP-001 — Finance/Treasury Ledger, Settlement & Inventory Valuation Authority Tranche Readiness

Status: READY FOR IMPLEMENTATION

## 1. Package identity

- Work package: FINANCE-IMP-001
- Module: Finance / Treasury
- Phase: P5 — Core application implementation
- Planning source: frozen PLAN-007 under `docs/plan/09-finans-kasa-banka/`
- Predecessor: WAREHOUSE-IMP-001 — COMPLETED / RUNTIME VERIFIED
- Scope-freeze baseline HEAD: 35eb688cc2f96d5128c24dc14fe4e6d040d28c34
- Committed migration baseline: 13

FINANCE-IMP-001 is one broad coherent implementation tranche. No Finance micro-package IDs are introduced.

## 2. Context receipt

Repository reconciliation established:

- `main` is authoritative and matched the expected baseline at scope freeze.
- The ten commits after Warehouse runtime-tested commit `81efd9c347118db24d8945f4d49e63bf99baaf70` are documentation-only.
- No Sales, Purchasing, Inventory, Warehouse source or migration drift occurred after the tested Warehouse position.
- Current migration baseline is 13.
- Inventory Ledger remains physical quantity truth.
- Reservation remains nonphysical.
- Sales owns Dispatch POST and exact physical source allocations.
- Purchasing owns Goods Receipt POST and Supplier Invoice DRAFT/match.
- Warehouse owns execution/count/scrap evidence and does not own a duplicate stock ledger.
- Finance Account/Cash/Bank/Valuation ledger authority is absent.
- Existing Sales, Purchasing, Inventory and Warehouse persistence shares `MarsDbContext` and supports ambient PostgreSQL transactions.
- Repository contains no Company/Currency/Settings authority.
- Current Purchasing Supplier Invoice authoritative calculation is TRY-only; current Sales commercial finalization already fails closed where non-TRY requires missing FX authority.

## 3. Active skill routing

Primary:
- accounting-finance-specialist
- erp-domain-specialist

Review:
- database-architect
- software-architect
- software-developer
- software-test-engineer
- security-specialist
- warehouse-operations-shipping-specialist for physical/valuation handoff
- ux-ui-specialist
- web-design-specialist

Repository decisions and frozen PLAN-007 override generic skill guidance where they differ. In particular, PLAN-007's balance-only settlement model forbids authoritative invoice-allocation/open-item truth.

## 4. Included authority

FINANCE-IMP-001 includes the following as one package:

### 4.1 Finance transaction and Party account authority
- normalized Finance transaction header and lifecycle;
- CUSTOMER receivable Account Ledger;
- SUPPLIER payable Account Ledger;
- posted immutable ledger entries with compensating reversal;
- durable operation/idempotency identity;
- document date, posting date, due date/value date where applicable;
- Party-role/currency separation;
- balance and aging projections from Account Ledger only.

### 4.2 Settlement / advances / refunds / role netting
- Collection: CUSTOMER CREDIT + Cash/Bank IN;
- Supplier Payment: SUPPLIER DEBIT + Cash/Bank OUT;
- explicit Customer Advance and Supplier Advance behavior when balance crosses sign;
- Customer Refund from eligible customer credit/advance;
- Supplier Refund from eligible supplier advance;
- explicit same-Party/same-company/same-currency CUSTOMER/SUPPLIER role netting;
- role-netting reason + approval + creator/approver SoD;
- no authoritative Invoice paid/open balance and no Collection/Payment-to-Invoice allocation.

### 4.3 Cash / Bank / Treasury
- Cash Account master and append-only Cash Ledger;
- Bank Account master and append-only Bank Ledger;
- opening balance as explicit ledger movement, not mutable master balance;
- normal Cash negative balance blocked;
- Bank negative balance blocked while no explicit overdraft policy authority exists;
- same-currency Cash/Bank/Bank treasury transfer with paired OUT/IN effects in one transaction;
- Cash Count with explicit discrepancy adjustment and approval/SoD for non-zero difference.

### 4.4 Posting periods and corrections
- Finance Posting Period states OPEN / FROZEN / CLOSED;
- server-side posting-date gate;
- FROZEN exceptional override only with dedicated permission, reason and approval;
- CLOSED normal posting/backdating blocked;
- CLOSED-period correction posts compensating history in an eligible open period;
- high-risk reopen requires separate permission, reason, approval and audit;
- posted Finance history is never silently updated/deleted.

### 4.5 Bank statement evidence / reconciliation
- normalized statement import batch/evidence rows;
- stable external id where supplied;
- deterministic fingerprint duplicate evidence when provider id is absent;
- statement evidence never mutates Bank Ledger by import alone;
- reconciliation match records with explicit matched amount;
- many-to-many / partial / aggregate matching;
- suggestion state has no posting authority;
- reversed matched Bank movement creates reconciliation exception/review, never silent remap.

The tranche implements normalized evidence ingestion and reconciliation authority, not a provider/file-specific parser.

### 4.6 Customer credit / risk / hold
- Finance-owned customer credit limit;
- Finance-owned manual hold;
- deterministic exposure projection from positive CUSTOMER receivable, Sales-authoritative confirmed/uninvoiced exposure and eligible customer credit;
- Sales consumes/revalidates Finance hold/limit state at the relevant commercial command boundary;
- no Party-master mutable financial balance/risk authority.

### 4.7 Inventory valuation / cost authority
- perpetual moving weighted-average valuation;
- valuation pool grain: Company + Product + stock-relevant Variant + Base UOM + base currency;
- Warehouse/Location does not split the valuation pool;
- Inventory Ledger remains quantity truth;
- Finance Valuation Ledger remains value truth;
- internal Warehouse Transfer has no valuation quantity/value effect.

Included source effects:
- Goods Receipt provisional carrying-value IN;
- Dispatch carrying-value OUT at then-current moving average;
- dispatched-not-invoiced cost bridge;
- Sales Invoice COGS recognition from eligible bridge only;
- Supplier Invoice receipt-linked late price-cost delta allocation across on-hand / bridge / already-recognized COGS;
- negative Count carrying-value removal and cost/write-off;
- positive Count valuation at current moving average when valid, otherwise explicit approved unit valuation;
- Scrap carrying-value removal and write-off;
- exact compensating valuation reversal lineage.

## 5. Authoritative persistence boundary

Finance uses a dedicated `finance` schema.

Authoritative/coordination tables for this tranche:

- `finance.transactions`
- `finance.account_ledger_entries`
- `finance.cash_accounts`
- `finance.cash_ledger_entries`
- `finance.bank_accounts`
- `finance.bank_ledger_entries`
- `finance.posting_periods`
- `finance.inventory_valuation_pools`
- `finance.inventory_valuation_entries`
- `finance.dispatch_cost_bridge_entries`
- `finance.dispatch_cost_bridge_consumptions`
- `finance.statement_import_batches`
- `finance.statement_lines`
- `finance.reconciliation_matches`
- `finance.customer_risk_controls`
- `finance.cash_counts`

Rules:
- `inventory_valuation_pools` identifies and serializes the valuation grain; it is not a mutable carrying-value source of truth.
- current Party/Cash/Bank/valuation balances are derived from append-only ledger/effect rows.
- bridge consumption is normalized by exact Dispatch source portion and Sales Invoice line/effect.
- public identity uses UUID; internal relational keys follow existing BIGINT conventions.
- money/value uses decimal/NUMERIC, never float/double.
- existing `numeric(28,9)` monetary/value convention is retained for Finance amount/base-value storage unless generated EF evidence demonstrates a narrower compatible requirement.
- FX/rate snapshot fields, when structurally present, use decimal and remain non-authoritative until the non-TRY gate is implemented.

Forbidden:
- mutable Party current balance;
- mutable Cash/Bank current balance;
- authoritative Invoice paid/open balance;
- payment/collection-to-Invoice allocation authority;
- imported statement row as Bank Ledger;
- mutable Product average cost;
- silent update/delete of posted Finance/valuation history.

## 6. Posting and reversal model

Every accepted POST:
1. validates authentication/permission/company/branch scope;
2. validates document/business state;
3. validates posting period;
4. validates durable operation/idempotency identity;
5. locks the authoritative source and affected monetary/valuation grain required for concurrency;
6. creates append-only ledger/effect rows;
7. updates only source document/work state that the owning module controls;
8. writes audit/outbox evidence;
9. commits one PostgreSQL transaction.

Reversal:
- creates linked compensating Finance entries;
- copies/reverses the original accepted transaction/base values;
- keeps original history;
- revalidates downstream bridge/reconciliation/cost dependencies;
- never restores physical stock unless the owning physical module separately performs its linked reversal.

## 7. Cross-module atomic transaction contracts

Existing ambient `MarsDbContext` transaction composition is reused.

### Sales Dispatch POST
Sales remains command owner.

One PostgreSQL transaction:
- validate/lock Dispatch;
- Inventory exact physical STOCK OUT per Sales-owned source allocation;
- consume Reservation;
- Finance valuation OUT at current moving average;
- create exact dispatched-not-invoiced bridge portion(s);
- complete Sales Dispatch POST.

No COGS is recognized at Dispatch.

### Sales Dispatch reversal
- Sales/Inventory reverse exact original physical effects;
- Finance reverses exact valuation/bridge effects;
- if bridge value has already been consumed to COGS, direct reversal is blocked or requires compensating Finance correction preserving source lineage;
- no posted row deletion.

### Sales Invoice POST / REVERSE
Sales remains Invoice document owner; Finance owns financial effects.

POST in one transaction:
- validate immutable DRAFT snapshot and source eligibility;
- CUSTOMER Account Ledger DEBIT for invoice gross receivable;
- for Dispatch-sourced eligible quantity, consume exact bridge value into COGS;
- no second physical STOCK OUT and no second inventory carrying-value OUT;
- transition Invoice to POSTED.

REVERSE:
- CUSTOMER compensating Account Ledger entry;
- COGS compensation back to bridge/correction state as lineage permits;
- physical stock is not restored;
- transition document by explicit reversal semantics.

Order-sourced/direct Invoice may create receivable but cannot invent Dispatch cost basis; COGS is recognized only where eligible bridge lineage exists.

### Purchasing Goods Receipt POST
Purchasing remains Goods Receipt owner.

For STOCKABLE lines, one transaction:
- Inventory STOCK IN to QUARANTINE;
- Finance provisional valuation IN;
- complete Goods Receipt POST.

Provisional carrying value uses the frozen KDV-exclusive Purchasing commercial basis before tax: accepted line price less line/document discounts, with source snapshot lineage. No policy for capitalizing non-recoverable tax is invented in this tranche.

SERVICE/non-stock receipt lines create no Inventory/valuation movement.

### Goods Receipt reversal
- exact compensating Inventory movement;
- exact compensating provisional valuation where downstream state permits;
- if Supplier Invoice late-cost/other downstream valuation has made direct reversal inconsistent, fail closed pending compensating flow;
- no payable effect merely from receipt reversal.

### Supplier Invoice POST / REVERSE
Purchasing remains Supplier Invoice document/match owner; Finance owns financial effects.

POST:
- requires match state eligible to post;
- SUPPLIER Account Ledger CREDIT for invoice gross payable;
- no second physical STOCK IN;
- for receipt-sourced stockable lines, calculate source-linked late price-cost delta against provisional pre-tax basis and allocate deterministically to on-hand valuation, dispatch bridge and recognized COGS;
- direct financial-only Supplier Invoice remains non-stock and requires its existing exception approval evidence;
- transition Supplier Invoice to POSTED.

REVERSE:
- compensating SUPPLIER Account Ledger entry;
- compensating late-cost effects;
- no physical stock reversal.

### Warehouse Count
Negative discrepancy:
- Warehouse physical OUT + Finance current-moving-average carrying-value OUT/write-off atomically.

Positive discrepancy:
- if valid positive valuation pool average exists, use it;
- otherwise require explicit Finance unit valuation + dedicated permission + reason + approval;
- Warehouse physical IN and Finance valuation IN commit atomically;
- silent zero-cost positive stock is forbidden.

### Warehouse Scrap
Warehouse remains physical Scrap owner.

POST:
- exact physical Inventory OUT;
- current moving-average carrying-value removal;
- Finance write-off classification/effect;
- one PostgreSQL transaction.

## 8. Currency and base-value rule for current repository

Current operational authoritative posting is intentionally restricted to TRY because the repository has no Company base-currency authority and no implemented authoritative FX/rate service.

For FINANCE-IMP-001 normal posting:
- transaction currency = TRY;
- base currency = TRY;
- base amount = transaction amount;
- current repository minor-unit behavior remains two decimals for accepted commercial TRY snapshots.

Non-TRY source documents may remain representable as draft/commercial data, but Finance POST fails closed until the missing authority exists.

Fail-closed / not implemented in this tranche:
- cross-currency Treasury/FX Transfer POST;
- realized FX posting;
- unrealized period-end revaluation;
- TCMB rate fetching/cache authority;
- manual FX override posting.

This is an implementation gate, not a new business assertion that every future Company must use TRY.

## 9. Exact exclusions / genuine blockers

Excluded because the necessary upstream authority/policy/provider contract is absent:

- non-TRY authoritative monetary posting and FX/revaluation execution;
- provider-specific bank API/file adapters, including format-specific MT940 behavior;
- outbound bank/provider payment execution;
- standalone landed-cost document engine and configurable custom allocation-driver administration;
- RMA/physical-return-based refund entitlement until Returns implementation exists;
- explicit Bank overdraft policy administration; absent policy means negative Bank balance remains blocked;
- statutory General Ledger / Chart of Accounts / e-ledger / tax filing;
- Checks/Promissory Notes implementation;
- production deployment;
- Full Test Day.

No provider/legal/business policy is fabricated to close these gaps.

## 10. Migration strategy

Baseline: 13 committed migrations.

FINANCE-IMP-001 migration strategy:
- additive Finance schema/model only;
- first Finance migration is migration #14 and is named for the broad authority tranche, e.g. `FinanceImp001LedgerSettlementValuationAuthority`;
- use `Migrations/Finance`;
- cross-module source contracts use existing UUID/source lineage where possible rather than copying commercial/physical truth;
- no destructive rewrite of Sales/Purchasing/Inventory/Warehouse history;
- no silent historical valuation backfill.

Pre-Finance physical history remains valid physical truth. Any future historical Finance reconstruction requires a separate deterministic, auditable backfill design and evidence; it is not inferred by this tranche.

## 11. API / Web boundary

Protected API:
- `/api/v1/finance` for Finance-owned transactions, ledgers, cash/bank, periods, statement/reconciliation, risk and valuation reads/actions;
- Sales Invoice POST/REVERSE remains under Sales command/API ownership and calls a Finance-owned contract;
- Supplier Invoice POST/REVERSE remains under Purchasing command/API ownership and calls a Finance-owned contract;
- Warehouse Count/Scrap remains under Warehouse ownership and calls Finance valuation contracts.

Mars.Web:
- `/finance` workspace;
- dense keyboard-oriented Finance screens using Mars.UI/Mars.Grid/Mars.Lookup;
- clear DRAFT/POSTED/REVERSED/period/reconciliation states;
- ledger-derived balances are read-only;
- POST/REVERSE/high-risk approval actions show expected effects;
- no editable stock/balance/current-cost authority.

## 12. Security / SoD

Server-side controls cover:
- Finance permission namespace frozen in PLAN-007;
- Company/Branch scope;
- Cash/Bank account scope where applicable;
- role-netting creator != approver;
- non-zero Cash Count adjustment SoD;
- manual positive Count valuation SoD;
- FROZEN-period override/reopen approval;
- sensitive Bank/IBAN masking on reads/exports;
- raw statement evidence access restrictions;
- durable idempotency and audit correlation.

UI visibility is not authorization.

## 13. Normal acceptance evidence

Normal implementation verification only:

- Release build;
- frontend targeted tests for Finance workspace;
- targeted Finance unit/invariant tests;
- targeted Sales/Purchasing/Warehouse cross-module invariant tests;
- EF pending-model check;
- migration safety from baseline 13 to Finance migration(s);
- protected Finance API unauthenticated smoke;
- OpenAPI Finance surface smoke;
- absence checks for forbidden Invoice allocation/current-balance/Product-average-cost authority;
- TEST deploy only when the implementation/migration is ready for normal tranche verification.

Required targeted invariants include:
- duplicate Finance POST does not duplicate ledger effects;
- Sales Invoice does not second-stock-out;
- Supplier Invoice does not second-stock-in;
- Dispatch valuation creates bridge but not COGS;
- Sales Invoice consumes bridge once;
- Goods Receipt valuation and Inventory IN are atomic;
- positive Count cannot post zero-cost;
- Scrap physical/value effects are atomic;
- role balances do not auto-net;
- statement import does not change Bank Ledger;
- CLOSED/FROZEN posting gates are enforced.

Not run during normal implementation:
- Full Test Day;
- broad concurrency/load/performance;
- full authenticated IDOR/permission matrix;
- provider sandbox/production bank behavior;
- backup/restore;
- production deployment.

## 14. Readiness decision

No blocker prevents starting the TRY/base-currency Finance authority substrate and its cross-module valuation/posting integration.

Known unsupported non-TRY/provider/policy surfaces are explicit fail-closed exclusions rather than hidden assumptions.

Decision:
- scope is FROZEN;
- work package is FINANCE-IMP-001;
- status is READY FOR IMPLEMENTATION;
- implementation may begin on `main` as the same broad tranche.

# Sales Reporting Contract

Status: FROZEN planning semantics for PLAN-002. Reports remain projections/read models.

## 1. Accounting and operational separation

Operational:
- Quote conversion progress;
- Order reserved/shipped/invoiced/remaining;
- Dispatch state.

Financial:
- posted Invoice revenue/receivable context;
- COGS at Sales Invoice POST;
- customer current-account balance from Finance ledger.

Do not use Dispatch as revenue or COGS recognition by default.

## 2. Collection reporting — B001

Frozen balance-only model:
- customer current-account balance is authoritative Finance-derived result;
- no authoritative Invoice paid/unpaid/open-amount report;
- no settlement/open-item aging by Invoice allocation.

If future reporting needs invoice-age analytics, it must be explicitly defined as analytical inference, not settlement truth.

## 3. Sales Summary

Preferred financial grain:
- posted Sales Invoice / Invoice line.

Must distinguish:
- transaction currency;
- base currency;
- immutable posted FX rate/source/date;
- returns/credits;
- reversed documents.

COGS:
- recognized at Invoice POST under B005.
- gross-margin reporting must source revenue and COGS from compatible posted financial records.

## 4. Customer/Product Sales

Use posted Invoice line as financial sales basis unless a future Reporting plan explicitly defines another KPI.

Master changes do not rewrite historical snapshots.

## 5. Order Status

Required line measures:
- effective ordered quantity;
- reserved;
- shipped;
- invoiced;
- cancelled remainder;
- remaining-to-ship;
- remaining-to-invoice;
- effective order version/amendment state.

Source:
Sales authority + linked Inventory/Dispatch/Invoice authority or rebuildable projection.

## 6. Quote conversion

PLAN-002 freezes operational quantity behavior:
- repeated partial conversion allowed;
- line remaining conversion quantity is deterministic;
- fully converted when all lines remaining = 0.

Exact management KPI formula (count/value denominator) remains Reporting-phase decision.

## 7. Currency/FX

Financial reports:
- preserve transaction currency;
- preserve posted FX source/date/rate;
- base-currency conversion for posted Invoice uses its immutable snapshot;
- do not revalue historical posted Sales using today's rate.

Default Sales Invoice FX policy:
- TCMB döviz alış;
- invoice/tax-event document date;
- latest prior published business day when no rate exists;
- audited override where permitted/approved.

## 8. Rounding

Report totals must aggregate posted rounded line/tax amounts, not independently recalculate using binary floating point or current policy.

TRY posted money uses 2 decimals.
Other currencies use configured/ISO minor unit.

## 9. Exclusions

Each final report contract explicitly handles:
- DRAFT;
- CANCELLED;
- REVERSED;
- superseded Quote revisions;
- inactive/cancelled Order remainder.

Saved views/caches remain non-authoritative.

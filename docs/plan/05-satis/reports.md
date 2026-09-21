# Sales Reporting Contract

Status: planning-level catalog. KPI formulas are not finalized unless source rules are sufficient.

Reports are projections/read models. They are never authoritative mutable Sales state.

## 1. V38 report mapping

V38 centralizes Sales reporting in the Report Center.

Mapped concepts:
- sales_report → central sales_summary report
- Sales Summary
- Customer-based Sales
- Product-based Sales
- Order Status
- Dispatch Report
- Sales Return Report
- Contact annual performance
- Product Sales / Top Customers / Annual Performance

Decision:
MERGE repeated module-level Sales report links into canonical Reporting views while preserving deep-links from Sales/Contact/Product contexts.

## 2. Required report contracts for later Reporting phase

### Sales Summary
Purpose:
- period commercial/financial Sales overview.

Potential grain:
- invoice or invoice line, depending final KPI.

BLOCKED formula details:
- whether revenue grain is posted invoice line only;
- return/credit treatment;
- FX conversion method;
- period/date dimension.

Do not use dispatch as revenue recognition without an explicit accounting decision.

### Customer-based Sales
Potential grain:
- posted invoice line grouped by customer snapshot/master mapping.

Needs:
- return/credit treatment;
- currency conversion;
- active/inactive customer handling.

### Product-based Sales
Potential grain:
- posted invoice line grouped by product snapshot/master identity.

Needs:
- returned quantity/credit policy;
- UOM normalization;
- currency conversion.

### Order Status
Operational, not accounting.

Required measures:
- order line ordered quantity
- reserved quantity
- shipped quantity
- invoiced quantity
- remaining-to-ship
- remaining-to-invoice
- order dates/due dates/status.

Source:
authoritative Sales documents plus linked Reservation/Dispatch/Invoice data or projection.

### Dispatch Report
Operational.

Measures:
- dispatch count
- shipped quantity
- package count
- carrier/handoff/delivery status
- dispatch delay candidates.

Revenue must not be derived from dispatch by default.

### Sales Return Report
Cross-module read model.

Must distinguish:
- physically returned quantity
- disposition
- financially credited quantity/value
- refunded amount/state

Physical return and financial credit cannot be collapsed into one boolean.

## 3. KPI candidates

These are future contract candidates only.

### Quote conversion
Need owner/reporting decision:
- numerator: accepted/converted quote count or value?
- denominator: all eligible quotes or only sent quotes?
- revision grain?
No formula frozen in PLAN-002.

### Order cycle time
Potential:
confirmed order timestamp → completion/delivery timestamp.
Exact endpoint and treatment of partial orders remain Reporting decision.

### Fill rate
Potential quantity-based fulfilment metric.
Exact numerator/denominator and timing remain Reporting decision.

### OTIF
Requires promised date, delivery completion and full quantity rules.
Formula not frozen here.

### Return rate
Requires physical/financial population definition.
Formula not frozen here.

### Gross margin
Requires SALES-B005 cost recognition/cost source plus revenue/return/FX rules.
BLOCKED until Finance/Costing contracts exist.

## 4. Currency and time rules

Every financial report later must define:
- transaction currency
- company base currency
- conversion date/rate source
- timezone
- document date vs posting date
- partial current period handling.

No mixed-currency naked totals.

## 5. Excluded statuses

Reporting contracts must explicitly state exclusions such as:
- draft
- cancelled
- reversed
- superseded quote revisions

Exact status filtering is report-specific and cannot be globally guessed.

## 6. Access

Reports honor:
- company scope
- branch scope where relevant
- actor permissions
- Sales vs Finance data sensitivity.

Saved views/filters do not become new authoritative datasets.

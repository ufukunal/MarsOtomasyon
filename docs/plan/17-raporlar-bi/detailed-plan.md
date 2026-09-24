# Reporting / BI — Detailed Plan

Status: DETAILED PLANNING FROZEN

## V38 product surface
Ürün Satış Raporu, Ürün Satınalma Raporu, Stok Yaşlandırma, Nakit Akış, Kârlılık, Üretim Analitiği, Kalite Analitiği, Rapor Kataloğu, Rapor Tasarımcısı, Print Center, Planlı Raporlar.
Some V38 patches mark designer/print/scheduled routes deprecated; therefore they are reference concepts, not mandatory implementation forms.

## Ownership
Reporting owns definitions, projections/query models, saved views, schedules and exports. It never owns transactional truth.

## KPI contract
Every metric must freeze: business name, formula, grain, dimensions, source authority, included/excluded states, date/time basis, currency/base-currency rule, filters, null/late-data policy, refresh/staleness indicator.

## Records
ReportDefinition, MetricDefinition, SavedView, FilterSnapshot, ExportJob, Schedule, ScheduleRecipient, RunEvidence, ProjectionWatermark.

## Core report families
Sales, Purchasing, Party balances/statement, Inventory aging/movement/turnover, Cash flow, Bank/Cash balances, Profitability, Production, Quality, supplier/customer performance.

## Workflow
Definition DRAFT -> ACTIVE -> RETIRED; versioned revisions.
Scheduled run: QUEUED -> RUNNING -> COMPLETED | FAILED.
Saved filter snapshot immutable per generated artifact.

## Security
Row/company scope enforced server-side before query/export. Export does not bypass sensitive-field permissions. Scheduled report executes with explicit owner/service authorization model.

## API/UI
/api/v1/reports/catalog, /views, /runs, /exports, /schedules.
Dashboard/drilldown/list/export UI follows Mars.Grid patterns.

## Data/performance
Rebuildable projections/materialized read models allowed. PostgreSQL remains authority. Cache may accelerate but not define figures. Watermarks/staleness visible.

## Acceptance
Formula fixtures, state/date/currency edge cases, permission/row isolation, projection rebuild, export parity, schedule snapshot and large-result pagination tests.

## UNKNOWN
Exact report designer scope and BI engine technology are not frozen and are implementation-time technical decisions.

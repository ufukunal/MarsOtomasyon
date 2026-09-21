# Current Handoff

## Repository
- Repository: ufukunal/MarsOtomasyon
- Allowed branch: main
- Current HEAD: verify from Git at session start
- Master plan: docs/plan/master-project-plan.md
- Session protocol: docs/ai/session-execution-protocol.md

## Current phase
P2 — Core Commercial Workflow Planning: COMPLETED / FROZEN
P3 — Logical Database Model: READY

## Planning progress
Counting basis: master-project-plan section 8; only COMPLETED / FROZEN packages count.
- Master planning sequence: 9 / 30 = 30.0%
- P2 core commercial planning: 8 / 8 = 100.0%

## Completed predecessor
PLAN-009 — Returns / RMA workflow contract
Status: COMPLETED / FROZEN

Canonical contracts:
- docs/plan/11-iadeler-rma/README.md
- docs/plan/11-iadeler-rma/plan.md
- docs/plan/11-iadeler-rma/workflows.md
- docs/plan/11-iadeler-rma/forms.md
- docs/plan/11-iadeler-rma/data-contract.md
- docs/plan/11-iadeler-rma/permissions.md
- docs/plan/11-iadeler-rma/integrations.md
- docs/plan/11-iadeler-rma/reports.md
- docs/plan/11-iadeler-rma/acceptance-criteria.md
- docs/plan/11-iadeler-rma/full-test-day.md

## Frozen PLAN-009 decisions
- Returns owns case/authorization/source-target coordination, not Inventory or Finance ledgers.
- Customer return receipt POST is STOCK IN and starts QUARANTINE; it does not automatically credit/refund.
- Customer financial credit is CUSTOMER CREDIT; customer refund is separate Finance CUSTOMER DEBIT + Cash/Bank OUT.
- Supplier return shipment POST is STOCK OUT; it does not automatically adjust supplier balance.
- Supplier financial adjustment is SUPPLIER DEBIT; supplier refund is separate Finance SUPPLIER CREDIT + Cash/Bank IN.
- Physical, QC/disposition, financial adjustment and refund progress are separate dimensions.
- Source-linked is normal; source-less is an approved evidence-based exception and never fabricates a source.
- Partial return is supported; normal cumulative return cannot exceed eligible source quantity.
- Product/Variant/UOM/lot/serial/company/source compatibility is mandatory.
- Customer returns use Sales Dispatch physical lineage and Sales Invoice financial context where applicable.
- Supplier returns use Goods Receipt physical lineage and Supplier Invoice financial context where applicable.
- Purchase-return valuation follows current moving-average Finance policy.
- Customer-return cost/COGS correction remains Finance-owned and source-linked; source-less inbound cannot silently use zero valuation.
- Replacement/exchange is return + linked normal Sales workflow, not a second sales engine.
- Posted effects use reversal/compensation.
- No Invoice allocation/open-item authority or automatic Customer/Supplier role netting.

No external web research was required.
No SQL, migration, C#/API/TypeScript, deployment or heavy tests were added/run.

## Next safe work package
PLAN-010 — Logical database model
Target: docs/db/

P2 dependency gate is satisfied. P3 logical modeling may begin.
Do not write physical SQL migrations or application code in PLAN-010 unless a later explicit task authorizes them.

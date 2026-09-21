# Current Handoff

## Repository
- Repository: ufukunal/MarsOtomasyon
- Allowed branch: main
- Current HEAD: verify from Git at session start
- Master plan: docs/plan/master-project-plan.md
- Session protocol: docs/ai/session-execution-protocol.md

## Current phase
P2 — Core Commercial Workflow Planning

## Current task
PLAN-002 — Sales Domain Workflow Contract

Status: BLOCKED FOR OWNER DECISIONS

The known Sales workflow has been documented under:
docs/plan/05-satis/

Created planning contracts:
- README.md
- plan.md
- workflows.md
- forms.md
- data-contract.md
- permissions.md
- integrations.md
- reports.md
- acceptance-criteria.md
- full-test-day.md

No application code, SQL schema, migration or UI implementation was created.

## Locked Sales contracts

- Quote has no reservation/stock/account/cash posting effect.
- Sales Order is not physical stock-out and does not create receivable.
- Reservation is a non-physical commitment linked to Sales Order lines.
- Dispatch POST/finalization is the normal physical STOCK OUT point.
- Dispatch consumes/releases reservation quantity.
- Sales Invoice POST/finalization creates customer receivable.
- Dispatch-sourced invoice cannot post stock again.
- Collection is a separate Finance event.
- Physical return and financial credit/refund are separate.
- Partial shipment and partial invoicing preserve line-level source/target traceability.
- Posted history uses reversal/compensation instead of silent edit/delete.
- V38 Sales lists/details remain product/UX reference; V38 patch code is not production architecture.
- Sales Returns route is merged into the canonical Returns Center in V38.
- Sales reports are centralized through the Report Center in V38.

## Owner decisions required before PLAN-002 can be frozen

SALES-B001 — Collection allocation model
Choose balance-only current account vs invoice/open-item allocation capability vs explicit hybrid.

SALES-B002 — Source-less/direct Sales Invoice stock behavior
Choose financial-only direct invoice vs combined stock+financial direct invoice under rules vs forbid source-less posting.

SALES-B003 — Quote conversion
Choose full conversion only vs line/quantity partial/repeated conversion.

SALES-B004 — Reservation trigger
Choose explicit user action vs automatic transition vs configurable policy.

SALES-B005 — Cost/COGS recognition
Choose financial cost recognition point; do not confuse inventory stock-out valuation with COGS.

SALES-B006 — Sales tax/discount/rounding/FX
Define tax-inclusive/exclusive sequence, discount order, rounding, precision and exchange-rate source/date/type.

SALES-B007 — Approval policy
Define whether Quote/Sales Order approval is mandatory and any thresholds/SoD rules.

SALES-B008 — Confirmed-order amendment
Choose amendment/version vs cancel remainder + new order vs another explicit controlled rule.

## Next safe work package

PLAN-002-DECISIONS — resolve SALES-B001 through SALES-B008 with the project owner.

Do not begin PLAN-003, database schema or application code before PLAN-002 is frozen.

## Required roles for decision session

Primary:
- erp-domain-specialist — convert owner choices into Sales document/state/quantity rules.
- accounting-finance-specialist — validate B001/B002/B005/B006 financial consequences.

Reviewers:
- mba-business-manager — operational impact and control burden.
- warehouse-operations-shipping-specialist — B002/B004/B008 stock/warehouse consequences.
- database-architect — ensure choices can be represented without duplicate truth.
- software-architect — transaction/module boundaries.
- software-test-engineer — ensure decisions become verifiable invariants.
- ux-ui-specialist — action/state visibility implications.

## Test policy

No heavy tests. PLAN-002 remains planning-only.
Full Test Day scenarios are already listed in docs/plan/05-satis/full-test-day.md.

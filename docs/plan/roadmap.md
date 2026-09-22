# MarsOtomasyon Project Roadmap

The canonical end-to-end roadmap is now:
- `docs/plan/master-project-plan.md`

This file remains a compact phase index. If this file conflicts with the master plan, the master plan wins unless a newer explicit owner decision says otherwise.

## P0 — Governance and execution protocol
Status: COMPLETED

Includes:
- AI commands
- skill system/router
- planning standard
- task/state/handoff
- role-driven session execution
- SESSION REPORT
- standalone NEXT PROMPT protocol

## P1 — Foundation / Framework contract
Status: COMPLETED — PLANNING ONLY

Canonical:
- `docs/plan/01-foundation/framework-plan.md`

No application framework code has been implemented yet.

## P2 — Core commercial workflow planning
Status: COMPLETED / FROZEN

Order:
1. Sales
2. Parties/Cariler
3. Product/Inventory Master
4. Purchasing
5. Warehouse
6. Finance/Treasury
7. Checks/Notes
8. Returns/RMA

Completed through Returns/RMA; see project-state and completed tasks.

## P3 — Logical database model
Status: COMPLETED / FROZEN

Begins only after core workflows are sufficiently frozen.

## P4 — Foundation implementation
Status: READINESS DECISION GATES

Implements accepted framework contract.

## P5 — Core application implementation
Status: NOT STARTED

Parties → Products → Inventory → Sales → Purchasing → Warehouse → Finance → Returns → Checks/Notes.

## P6 — Operations
Status: NOT STARTED

Quality, Production, Subcontracting, Import/Container, MRP, Capacity, Maintenance, Service/Warranty, Sample/Consignment, Contracts/Periodic, Fixed Assets, Carrier Performance.

## P7 — Commerce and external channels
Status: NOT STARTED

Commerce Core, B2B, Architect Portal, WooCommerce and marketplaces. Provider capabilities must be verified at implementation time.

## P8 — Communications / Files / Device
Status: NOT STARTED

Email, SMS, WhatsApp, Push, files, scanner, barcode, printer/device routing.

## P9 — Reporting / BI / Management / Control
Status: NOT STARTED

Dashboards, KPI contracts, Mars.Reporting, report designer, CRM, SoD.

## P10 — Cross-platform shells
Status: NOT STARTED

Web/Desktop/Mobile common client core and platform adapters.

## P11 — Full Test Day
Status: DEFERRED BY POLICY

Full regression, PostgreSQL integration, browser E2E, cross-platform, provider, security, backup/restore, concurrency, performance and ledger/accounting invariants.

## P12 — Release hardening
Status: NOT STARTED

Production deployment reproducibility, migration rehearsal, backup/restore evidence, rollback, observability, capacity and operational runbooks.

## Current next action

P4 — Foundation implementation readiness / decision-gate resolution.

Before code:
- classify Foundation technology gates required for the first thin framework slice;
- record explicit owner decisions for required-now gates;
- keep deferrable gates explicit;
- then activate a separate implementation work package.

Exact section-8 planning metric remains 9 / 30 = 30.0% until Quality (conceptual item 10) is COMPLETED / FROZEN.

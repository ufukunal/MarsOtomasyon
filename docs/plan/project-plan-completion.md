# MarsOtomasyon — Project Planning Completion Baseline

Status: COMPLETED / FROZEN — PORTFOLIO PLANNING

Repository: ufukunal/MarsOtomasyon
Branch: main

## Purpose

Close the project-planning phase without creating a micro-plan for every future implementation step.

This document completes the master planning coverage for the full 30-item sequence at portfolio level.

It does NOT claim implementation completion.

Implementation remains evidence-driven and each active module still requires a concrete readiness tranche before code mutation when its turn arrives.

## Planning policy

The following planning levels are now distinguished:

1. Portfolio planning — complete for all 30 master items.
2. Frozen detailed domain planning — already complete for Foundation and P2 commercial core plus PLAN-010 logical database.
3. Implementation readiness — created only when a module becomes active.
4. Implementation — requires code, migration, tests and runtime evidence.
5. Full Test Day / Release Hardening — remain future execution phases.

This prevents the project from spending sessions writing repetitive speculative plans for modules that are not yet executable.

## Completed detailed planning foundation

Already frozen in repository:
- 01 Foundation/Framework
- 02 Sales
- 03 Parties/Cariler
- 04 Product/Inventory Master
- 05 Purchasing
- 06 Warehouse
- 07 Finance/Treasury
- 08 Checks/Notes
- 09 Returns/RMA
- PLAN-010 logical database model

These detailed plans remain authoritative.

## Remaining master sequence — frozen portfolio envelopes

### 10 Quality

Purpose:
- inbound/in-process/final quality controls, inspection evidence, disposition decision and nonconformance visibility.

Depends on:
- Inventory/Warehouse
- Purchasing
- Production where applicable

Owns:
- quality inspection/work/evidence and quality decision workflow.

Must not own:
- physical stock truth;
- Product master;
- Finance valuation.

Implementation gate:
- exact inspection types, sampling rules, acceptance/rejection authority and disposition handoff are frozen when Quality becomes active.

### 11 Production

Purpose:
- production order, material issue, WIP, completion/receipt and production traceability.

Depends on:
- Product
- Inventory/Warehouse
- MRP later
- Finance costing boundary

Must preserve:
- material issue = physical Inventory OUT;
- completion/receipt = physical Inventory IN;
- production cost/value remains Finance/Costing authority.

Implementation gate:
- BOM/routing/work-center and WIP recognition semantics must be source-backed before coding.

### 12 Subcontracting

Purpose:
- company-owned material sent to subcontractor, usage/return/scrap/output and service-cost linkage.

Must not model subcontract transfer as Sales.

Depends on:
- Inventory
- Purchasing
- Production
- Finance costing.

### 13 Import / Container

Purpose:
- import shipment/container quantity visibility, landed-cost evidence and source linkage.

Depends on:
- Purchasing
- Inventory
- Finance
- external document/file layer.

Physical quantity and landed-cost value remain separate authorities.

### 14 Commerce / B2B / API

Purpose:
- normalized external channel/order/catalog integration over Mars core.

Includes portfolio scope:
- Commerce Core
- B2B Portal
- Architect Portal
- WooCommerce
- Trendyol
- Hepsiburada
- N11
- ÇiçekSepeti
- Idefix
- catalog mapping
- channel content overrides
- channel pricing
- sellable-stock projection
- order ingest
- returns/messages/webhooks/reconciliation
- provider error/observability center.

Rules:
- external order normalizes into Mars Sales authority;
- provider state never becomes stock/accounting truth;
- provider capability must be verified against current provider documentation at implementation time.

### 15 Communications / Files

Purpose:
- email/SMS/WhatsApp/push/notification center/templates/file storage and download policy.

Depends on:
- Foundation outbox/worker
- Security
- provider adapters selected later.

Rules:
- external send is asynchronous side effect;
- DB commit is not provider-delivery success;
- file authorization and malware/security handling are explicit at implementation.

### 16 Reporting / BI

Purpose:
- operational and management reporting over authoritative records and rebuildable projections.

Includes:
- dashboard
- KPI dictionary
- saved reports/views
- profitability
- operational KPIs
- reporting engine/designer where justified.

Every KPI requires:
- formula
- grain
- filters
- currency
- time basis
- state basis
- authoritative source.

Reporting never becomes transaction authority.

### 17 Settings / System

Purpose:
- company-scoped configuration authorities needed by active modules.

Candidate authorities when source-backed:
- numbering
- commercial policies
- currency/minor-unit metadata
- approval policies
- provider configuration
- feature/system parameters
- role/group administration if accepted later.

Rules:
- configuration cannot silently change posted history;
- security-sensitive administration requires audit/SoD as applicable.

### 18 MRP

Purpose:
- demand/supply planning and material requirement proposals.

Depends on:
- Sales demand
- Purchasing
- Product/BOM
- Inventory
- Production.

MRP output is planning/proposal, not physical stock truth.

### 19 Capacity Planning

Purpose:
- work-center/resource capacity demand, available capacity and overload visibility.

Depends on:
- Production/routing/resource calendars.

Capacity plan is projection/planning authority, not actual production completion.

### 20 Maintenance

Purpose:
- asset/equipment maintenance requests, preventive schedules, work orders, parts usage and downtime evidence.

Depends on:
- Fixed Assets/equipment identity where applicable
- Inventory for spare parts
- Purchasing for external service/material
- notifications/scheduling.

### 21 Service / Warranty

Purpose:
- service cases, warranty eligibility/evidence, repair/replacement workflow and service history.

Depends on:
- Parties
- Product/Serial
- Inventory
- Returns where physical return/replacement occurs.

Must not duplicate Returns physical authority or Finance refund authority.

### 22 Sample / Consignment

Purpose:
- company-owned goods issued as samples or consignment with explicit custody/ownership semantics.

Depends on:
- Inventory
- Parties
- Sales/Finance when conversion to sale occurs.

Must not silently classify consignment issue as revenue or final sale.

### 23 Contracts / Periodic Operations

Purpose:
- contracts, recurring obligations/actions, scheduled commercial operations and renewal/expiry evidence.

Depends on:
- Parties
- Product/Services
- Sales/Purchasing/Finance according to generated transaction type.

Scheduled generation creates normal owning-module documents; contract module does not bypass them.

### 24 Fixed Assets

Purpose:
- fixed asset identity, lifecycle, acquisition reference, location/custodian and depreciation/financial linkage.

Depends on:
- Purchasing
- Finance
- Maintenance.

Physical asset registry and accounting depreciation/value authority remain distinguishable.

### 25 CRM

Purpose:
- lead/opportunity/activity/customer-interaction pipeline using Party as customer identity.

Depends on:
- Parties
- Sales
- Communications.

CRM opportunity value is not Account Ledger truth.

### 26 Carrier Performance

Purpose:
- shipment/carrier service evidence and performance metrics.

Depends on:
- Dispatch/Warehouse shipping
- carrier integrations
- Reporting.

Metrics are projections; carrier event/provider state does not change Inventory truth by itself.

### 27 SoD / Control Center

Purpose:
- cross-module segregation-of-duties visibility, privileged-action review and control evidence.

Depends on:
- Foundation permission authority
- approval evidence
- audit
- module permission catalogs.

It does not replace module authorization checks.

### 28 Device Layer

Purpose:
- scanner, barcode, camera, printer routing, ESC/POS, ZPL/TSPL/RAW, device registration and offline operation identity.

Depends on:
- Foundation client/platform contracts
- Warehouse/mobile needs
- Print/file security.

Offline retries must preserve durable operation identity and server-side validation.

### 29 Full Test Day

Purpose:
- broad evidence run only when explicitly started.

Includes:
- full unit/regression
- PostgreSQL integration
- browser E2E
- Web/Desktop/Mobile
- accounting/ledger invariants
- concurrency/idempotency
- backup/restore
- security
- provider sandbox/reconciliation
- performance/load
- migration rehearsal
- print/device flows.

Entry gate:
- implementation scope intended for release is materially complete.

No routine development session should silently start Full Test Day.

### 30 Release Hardening

Purpose:
- production readiness and owner acceptance.

Includes:
- production topology verification
- secret storage/rotation
- migration rehearsal
- backup/restore proof
- rollback/forward-fix rehearsal
- monitoring/alerting
- runbooks
- capacity baseline
- release checklist
- owner acceptance.

Exit:
- project is release-ready according to master Definition of DONE.

## Phase execution order

Implementation/execution remains:

P5 Core application:
1. Parties
2. Products
3. Inventory
4. Sales
5. Purchasing
6. Warehouse
7. Finance/Treasury
8. Returns
9. Checks/Notes

Then:
- P6 Operations
- P7 Commerce/external channels
- P8 Communications/files/devices
- P9 Reporting/management/control
- P10 Cross-platform shells
- P11 Full Test Day
- P12 Release Hardening

The portfolio plan does not force every future module into a separate implementation package.
Broad coherent tranches are preferred.

## Readiness rule for future modules

When a future module becomes current, do only a short readiness pass:

1. verify main HEAD;
2. read its frozen portfolio envelope plus directly relevant detailed sources;
3. inspect current predecessor implementations;
4. freeze one broad coherent implementation tranche;
5. assign one implementation ID;
6. implement/test/deploy;
7. close with canonical evidence.

Do not repeat global project planning.

## UNKNOWN / deferred decisions

These remain implementation-time gates rather than reasons to keep project planning incomplete:
- Desktop shell technology;
- Mobile shell technology;
- exact object/file storage backend;
- exact structured logging/metrics stack;
- optional scheduling library beyond Worker;
- production secret store;
- production reverse proxy/tunnel;
- provider-specific current capabilities;
- Settings-owned numbering/policy details not yet implemented;
- exact device/provider adapters.

They must be resolved only when an active implementation depends on them.

## Planning completion decision

Master portfolio planning coverage:

30 / 30 = 100%

P2 detailed core commercial planning:

8 / 8 = 100%

PLAN-010 logical database model:

COMPLETED / FROZEN

This is planning completion only.

It is not implementation completion and does not change the current active implementation package.

Current active implementation remains repository-truth driven.

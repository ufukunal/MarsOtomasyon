# Completed Tasks

## PLAN-001 — Planning backbone and Foundation plan
**Status:** COMPLETED

Created:
- `docs/plan/project-state.yaml`
- `docs/plan/active-task.yaml`
- `docs/plan/planning-standard.md`
- `docs/plan/roadmap.md`
- `docs/plan/handoff/current.md`
- task tracking files
- decisions structure
- Foundation plan and acceptance criteria
- initial database domain dictionary and design principles

Evidence commit:
- `3a36a50a2978a7307ff395f79f00497ccc2ed59b`

## Governance groundwork
- Single allowed branch established: `main`
- New branch creation blocked at repository level
- PR requirement removed for direct-main workflow
- AI command protocol created
- AI skill system created and detailed
- Skill router created
- Mandatory NEXT PROMPT/autocomplete protocol created

This file records planning milestones, not application implementation.


## FRAMEWORK-001 — Foundation / Framework contract
**Status:** COMPLETED (PLANNING ONLY)

Created:
- `docs/plan/master-project-plan.md`
- `docs/plan/01-foundation/framework-plan.md`
- `docs/ai/session-execution-protocol.md`

Updated:
- `docs/ai/autocomplete.md`
- `docs/plan/ai-cmd.md`
- project state/handoff records

Locked:
- V38 remains product/UI reference, not production codebase.
- Foundation owns shared infrastructure, not domain policy.
- Modular-monolith dependency/transaction/API/UI framework boundaries are documented.
- Every session must produce SESSION REPORT and role-driven standalone NEXT PROMPT.

Not implemented:
- no application source code
- no domain SQL schema
- no framework runtime implementation

Evidence:
- master/framework/protocol commits are recorded in main history.

## PLAN-002 — Sales Domain Workflow Contract
**Status:** COMPLETED / FROZEN (PLANNING ONLY)

Frozen:
- Quote → partial/repeated Order conversion.
- Manual Reservation.
- Dispatch-only physical STOCK OUT.
- Invoice receivable + COGS; Invoice STOCK = NONE.
- Balance-only Collection without Invoice allocation.
- KDV-exclusive calculation sequence and deterministic rounding/FX snapshots.
- Conditional commercial-policy approval with creator != approver.
- Controlled-delta confirmed-order amendment with immutable processed history.

Planning outputs:
- `docs/plan/05-satis/README.md`
- `docs/plan/05-satis/plan.md`
- `docs/plan/05-satis/workflows.md`
- `docs/plan/05-satis/forms.md`
- `docs/plan/05-satis/data-contract.md`
- `docs/plan/05-satis/permissions.md`
- `docs/plan/05-satis/integrations.md`
- `docs/plan/05-satis/reports.md`
- `docs/plan/05-satis/acceptance-criteria.md`
- `docs/plan/05-satis/full-test-day.md`

Acceptance evidence:
- `a5f56208b44847cf74feba43934f6d25412e93ad`

Not implemented:
- no SQL/migration
- no C#/API
- no TypeScript/UI
- no deployment
- Full Test Day pending

## PLAN-003 — Party / Customer / Supplier model
**Status:** COMPLETED / FROZEN (PLANNING ONLY)

Frozen:
- one company-scoped Party master;
- PERSON / ORGANIZATION kinds;
- CUSTOMER and SUPPLIER multi-role model;
- legal/display identity, tax identity, contact and address ownership;
- Party Code role-neutral identity with Settings-owned numbering format;
- ACTIVE / INACTIVE / MERGED lifecycle;
- deterministic identity collision + warning-only fuzzy duplicate detection;
- logical audited merge with survivor/source lineage;
- Finance-owned balances, credit/risk/hold and settlement;
- historical document snapshot immutability;
- no automatic customer/supplier balance netting;
- no cross-company Party sharing.

Planning outputs:
- `docs/plan/03-cariler/README.md`
- `docs/plan/03-cariler/plan.md`
- `docs/plan/03-cariler/workflows.md`
- `docs/plan/03-cariler/forms.md`
- `docs/plan/03-cariler/data-contract.md`
- `docs/plan/03-cariler/permissions.md`
- `docs/plan/03-cariler/integrations.md`
- `docs/plan/03-cariler/reports.md`
- `docs/plan/03-cariler/acceptance-criteria.md`
- `docs/plan/03-cariler/full-test-day.md`

Acceptance evidence:
- `52628746f484919f370feae5cf7be3ab6c87d8ef`

Not implemented:
- no SQL/migration
- no C#/API
- no TypeScript/UI
- no deployment
- Full Test Day pending

## PLAN-004 — Product / Inventory master model
**Status:** COMPLETED / FROZEN (PLANNING ONLY)

Frozen:
- company-scoped Product master;
- GOODS / SERVICE distinction;
- SELLABLE / PURCHASABLE / STOCKABLE capabilities;
- optional Variant identity;
- deterministic Base/alternate UOM conversions;
- unambiguous barcode/GTIN mapping;
- normalized category/classification;
- company-scoped Warehouse and hierarchical Location masters;
- Inventory Ledger as physical quantity authority;
- non-physical Reservation separate from physical disposition;
- explicit on-hand / available / reserved / available-to-reserve semantics;
- NONE / LOT / SERIAL / LOT_SERIAL tracking;
- immutable historical Product/UOM snapshots;
- Finance-owned inventory valuation/cost policy.

Planning outputs:
- `docs/plan/04-urun-stok/README.md`
- `docs/plan/04-urun-stok/plan.md`
- `docs/plan/04-urun-stok/workflows.md`
- `docs/plan/04-urun-stok/forms.md`
- `docs/plan/04-urun-stok/data-contract.md`
- `docs/plan/04-urun-stok/permissions.md`
- `docs/plan/04-urun-stok/integrations.md`
- `docs/plan/04-urun-stok/reports.md`
- `docs/plan/04-urun-stok/acceptance-criteria.md`
- `docs/plan/04-urun-stok/full-test-day.md`

Acceptance evidence:
- `96c1855f200512d50009d1499aeaf7e4f96d88bb`

Not implemented:
- no SQL/migration
- no C#/API
- no TypeScript/UI
- no deployment
- Full Test Day pending

## PLAN-005 — Purchasing workflow contract
**Status:** COMPLETED / FROZEN (PLANNING ONLY)

Frozen:
- Purchase Order commitment only; no stock/payable.
- conditional exception-based approval with creator != approver.
- partial/short Goods Receipt.
- default-block over-receipt with explicit tolerance policy/approval.
- stockable Goods Receipt POST → STOCK IN → QUARANTINE.
- separate QC/disposition release to AVAILABLE.
- Goods Receipt no supplier payable.
- Supplier Invoice POST → payable; STOCK = NONE.
- stockable 3-way PO/Receipt/Invoice match.
- service/non-stock 2-way match.
- controlled direct service/non-stock financial-only invoice.
- default-block over-invoice and zero-default price variance tolerance.
- Purchasing calculation/FX aligned with frozen Mars central policy.
- Finance-owned Payment/settlement.
- physical Purchase Return vs financial supplier adjustment separation.
- immutable posted history/reversal model.

Planning outputs:
- `docs/plan/07-satinalma/README.md`
- `docs/plan/07-satinalma/plan.md`
- `docs/plan/07-satinalma/workflows.md`
- `docs/plan/07-satinalma/forms.md`
- `docs/plan/07-satinalma/data-contract.md`
- `docs/plan/07-satinalma/permissions.md`
- `docs/plan/07-satinalma/integrations.md`
- `docs/plan/07-satinalma/reports.md`
- `docs/plan/07-satinalma/acceptance-criteria.md`
- `docs/plan/07-satinalma/full-test-day.md`

Acceptance evidence:
- `a8ef551310d910bcc38c8a383f2a5d86839621d1`

Not implemented:
- no SQL/migration
- no C#/API
- no TypeScript/UI
- no deployment
- Full Test Day pending

## PLAN-006 — Warehouse operational contract
**Status:** COMPLETED / FROZEN (PLANNING ONLY)

Frozen:
- normal negative physical stock is blocked;
- AVAILABLE-only normal pick eligibility;
- FEFO for expiry-tracked stock, FIFO otherwise;
- audited strategy override without making expired/blocked stock eligible;
- Pick/Pack/Stage/Load are operational work states with no Sales stock posting;
- Sales Dispatch POST remains the single Sales STOCK OUT point;
- Goods Receipt POST remains the single Purchasing STOCK IN point;
- put-away/replenishment are internal location moves;
- Transfer ISSUE → TRANSIT → RECEIVE with partial receive and explicit loss reconciliation;
- ledger-snapshot stock count with intervening movement reconciliation;
- approved COUNT_ADJUSTMENT delta instead of stock overwrite;
- hard lot/serial/barcode mismatch blocking;
- Warehouse/Location deactivation blockers;
- approved scrap/disposal STOCK OUT with Finance-owned valuation;
- durable offline operation identity and idempotent retry/conflict handling.

Planning outputs:
- `docs/plan/06-ambar-depo/README.md`
- `docs/plan/06-ambar-depo/plan.md`
- `docs/plan/06-ambar-depo/workflows.md`
- `docs/plan/06-ambar-depo/forms.md`
- `docs/plan/06-ambar-depo/data-contract.md`
- `docs/plan/06-ambar-depo/permissions.md`
- `docs/plan/06-ambar-depo/integrations.md`
- `docs/plan/06-ambar-depo/reports.md`
- `docs/plan/06-ambar-depo/acceptance-criteria.md`
- `docs/plan/06-ambar-depo/full-test-day.md`

Acceptance evidence:
- `f0933c9992e16f4336e0f06d55fafbaa44da489f`

Planning progress after completion:
- master sequence: 6 / 30 = 20.0%
- P2 core commercial: 5 / 8 = 62.5%

Not implemented:
- no SQL/migration
- no C#/API
- no TypeScript/UI
- no deployment
- Full Test Day pending

## PLAN-007 — Finance / Treasury workflow contract
**Status:** COMPLETED / FROZEN (PLANNING ONLY)

Frozen:
- Account Ledger authority with separate CUSTOMER_RECEIVABLE / SUPPLIER_PAYABLE roles;
- balance-only Collection and Supplier Payment with no authoritative Invoice allocation/open-item state;
- explicit advances/refunds and permissioned dual-role Party netting;
- authoritative Cash Ledger / Bank Ledger;
- same-currency treasury transfer and FX transfer semantics;
- weighted-carrying realized FX and separate unrealized revaluation workflow;
- Bank statement evidence/import separated from Bank Ledger;
- explicit partial/many-to-many reconciliation matched amounts;
- OPEN / FROZEN / CLOSED Finance posting-period controls;
- perpetual moving weighted-average inventory valuation;
- Dispatch carrying-value removal + dispatched-not-invoiced cost bridge;
- Sales Invoice COGS recognition without second stock/value reduction;
- Supplier Invoice / landed-cost late cost source allocation;
- positive-count manual valuation protection and scrap write-off boundary;
- Finance-owned customer credit/risk/hold.

Planning outputs:
- `docs/plan/09-finans-kasa-banka/README.md`
- `docs/plan/09-finans-kasa-banka/plan.md`
- `docs/plan/09-finans-kasa-banka/workflows.md`
- `docs/plan/09-finans-kasa-banka/forms.md`
- `docs/plan/09-finans-kasa-banka/data-contract.md`
- `docs/plan/09-finans-kasa-banka/permissions.md`
- `docs/plan/09-finans-kasa-banka/integrations.md`
- `docs/plan/09-finans-kasa-banka/reports.md`
- `docs/plan/09-finans-kasa-banka/acceptance-criteria.md`
- `docs/plan/09-finans-kasa-banka/full-test-day.md`

Acceptance evidence:
- `55079aeda8b7345b392f55c8bc13d366b3a32a0c`

Planning progress after completion:
- master sequence: 7 / 30 = 23.3%
- P2 core commercial: 6 / 8 = 75.0%

Not implemented:
- no SQL/migration
- no C#/API
- no TypeScript/UI
- no deployment
- Full Test Day pending


## PLAN-008 — Checks / Promissory Notes workflow contract
**Status:** COMPLETED / FROZEN (PLANNING ONLY)

Frozen:
- incoming receipt CUSTOMER CREDIT + Finance instrument receivable; Cash/Bank only at actual settlement;
- outgoing delivery SUPPLIER DEBIT + Finance instrument payable; Cash/Bank only at actual clearing;
- custody and financial state separation;
- bank handoff custody-only;
- whole-remaining endorsement to eligible Supplier; partial endorsement forbidden in core;
- partial collection/payment with cumulative cap;
- bounce/protest/return and reversal compensation;
- immutable nominal amount/currency and snapshots;
- no Invoice allocation/open-item, no duplicate Account/Cash/Bank truth, no STOCK effect;
- permissions/SoD/company scope, FX and concurrency/idempotency boundaries.

Planning outputs:
- `docs/plan/10-cek-senet/README.md`
- `docs/plan/10-cek-senet/plan.md`
- `docs/plan/10-cek-senet/workflows.md`
- `docs/plan/10-cek-senet/forms.md`
- `docs/plan/10-cek-senet/data-contract.md`
- `docs/plan/10-cek-senet/permissions.md`
- `docs/plan/10-cek-senet/integrations.md`
- `docs/plan/10-cek-senet/reports.md`
- `docs/plan/10-cek-senet/acceptance-criteria.md`
- `docs/plan/10-cek-senet/full-test-day.md`

Planning progress after completion:
- master sequence: 8 / 30 = 26.7%
- P2 core commercial: 7 / 8 = 87.5%

Not implemented:
- no SQL/migration
- no C#/API/TypeScript
- no deployment
- Full Test Day pending


## PLAN-009 — Returns / RMA workflow contract
**Status:** COMPLETED / FROZEN (PLANNING ONLY)

Frozen:
- customer/supplier return case lifecycle and source lineage;
- source-linked normal path plus controlled approved source-less exception;
- customer receipt STOCK IN → QUARANTINE with separate financial credit/refund;
- supplier return shipment STOCK OUT with separate supplier adjustment/refund;
- independent physical/QC/financial/refund progress;
- partial return and cumulative source cap;
- Product/UOM/lot/serial validation;
- reversal/compensation and dependency handling;
- replacement as linked normal Sales flow;
- Finance-owned valuation/FX/refunds and Inventory-owned physical truth;
- no Invoice allocation/open-item or automatic cross-role netting;
- permissions/SoD/company/warehouse scope and concurrency/idempotency boundaries.

Planning outputs:
- `docs/plan/11-iadeler-rma/README.md`
- `docs/plan/11-iadeler-rma/plan.md`
- `docs/plan/11-iadeler-rma/workflows.md`
- `docs/plan/11-iadeler-rma/forms.md`
- `docs/plan/11-iadeler-rma/data-contract.md`
- `docs/plan/11-iadeler-rma/permissions.md`
- `docs/plan/11-iadeler-rma/integrations.md`
- `docs/plan/11-iadeler-rma/reports.md`
- `docs/plan/11-iadeler-rma/acceptance-criteria.md`
- `docs/plan/11-iadeler-rma/full-test-day.md`

Planning progress after completion:
- master sequence: 9 / 30 = 30.0%
- P2 core commercial: 8 / 8 = 100.0%

Not implemented:
- no SQL/migration
- no C#/API/TypeScript
- no deployment
- Full Test Day pending


## PLAN-010 — Logical Database Model
**Status:** COMPLETED / FROZEN (LOGICAL PLANNING ONLY)

Frozen:
- domain dictionary v2;
- bounded-context module/data ownership;
- logical entity catalog;
- normalized source-target relationships;
- separate Inventory/Account/Cash/Bank/Valuation ledger families;
- Reservation separate from physical disposition;
- Party multi-role and no automatic role netting;
- Sales/Purchasing/Returns quantity lineage;
- Checks/Notes custody vs Finance position separation;
- Finance transaction/FX/period/reconciliation/valuation model;
- immutable snapshots and rebuildable projections;
- keys/identity roles;
- logical constraints and concurrency/idempotency outcomes;
- access-pattern-driven index intent;
- migration/backfill/lock conventions.

Outputs:
- `docs/db/00-domain-dictionary.md`
- `docs/db/01-design-principles.md`
- `docs/db/02-module-ownership.md`
- `docs/db/03-entity-catalog.md`
- `docs/db/04-relationships.md`
- `docs/db/05-ledgers-and-finance.md`
- `docs/db/06-snapshots-and-projections.md`
- `docs/db/07-constraints-and-concurrency.md`
- `docs/db/08-index-access-patterns.md`
- `docs/db/09-migration-conventions.md`
- `docs/db/acceptance-criteria.md`
- `docs/db/full-test-day.md`

Exact section-8 planning metric remains:
- 9 / 30 = 30.0%
because section-8 item 10 is Quality, not PLAN-010 Logical Database Model.

Not implemented:
- no physical SQL/DDL
- no migration/EF model
- no C#/API/TypeScript
- no deployment
- Full Test Day pending


## FW-IMP-001 — Repository solution skeleton
**Status:** COMPLETED

Implemented:
- `Mars.slnx` using the .NET 10 SLNX solution format;
- central `net10.0` target in `Directory.Build.props`;
- `Mars.Domain`, `Mars.Contracts`, `Mars.Application`, `Mars.Infrastructure`, `Mars.Api`, `Mars.Worker`, `Mars.Device` project skeletons;
- project references encoding the accepted dependency DAG;
- targeted self-hosted Foundation build workflow.

Dependency evidence:
- Application -> Domain + Contracts
- Infrastructure -> Domain + Application
- Api -> Application + Contracts + Infrastructure
- Worker -> Application + Infrastructure
- Domain / Contracts / Device have no project references.

Deliberately deferred:
- Mars.Web to FW-IMP-006;
- test projects until meaningful behavior exists;
- EF Core/Npgsql packages, DbContext, SQL and migrations to FW-IMP-003;
- API host behavior to later API Foundation work.

Build evidence:
- verified commit: `212022601c177ec6bc8e8438c6188e747cbee2c5`
- workflow run: `35702737435`
- .NET SDK: `10.0.401`
- runtime: `10.0.12`
- restore: PASS
- Release build: PASS — 0 warnings, 0 errors
- project-reference verification: PASS

Full Test Day pending.


## FW-IMP-002 — Configuration/context/error primitives
**Status:** COMPLETED

Implemented:
- typed startup configuration validation contracts in `Mars.Application.Foundation.Configuration`;
- immutable correlation id and execution context carrying ActorId, CompanyId, optional BranchId and CorrelationId;
- deterministic Foundation error categories and success/failure result contracts;
- zero-external-dependency targeted test harness under `tests/Mars.Foundation.Tests`;
- Foundation build workflow now runs targeted FW-IMP-002 verification.

No new NuGet package was added.

Deliberately not implemented:
- EF Core/Npgsql persistence wiring;
- DbContext, SQL or migrations;
- authentication/identity provider;
- OpenAPI tooling;
- logging/metrics backend;
- Mars.Web/Mars.UI;
- deployment.

Verification:
- tested commit: `a74e1083790aaf652dcac7dc0d735f759fc765e7`
- workflow run: `35704843486`
- .NET SDK: `10.0.401`
- runtime: `10.0.12`
- restore: PASS
- Release build: PASS — 0 warnings, 0 errors
- targeted tests: PASS — 8 / 8
- project-reference verification: PASS

The first implementation run exposed a C# syntax error in `ConfigurationValidationIssue`; it was corrected in-scope and the final verification run passed.

Full Test Day pending.


## FW-IMP-003 — Persistence/migration baseline
**Status:** COMPLETED

Implemented:
- EF Core/Npgsql persistence baseline in `Mars.Infrastructure`;
- explicit runtime vs migration connection-option types;
- `MarsDbContext` with no domain entity mappings;
- design-time migration factory using `MARS_MIGRATION_CONNECTION_STRING`;
- local `dotnet-ef` tool manifest;
- canonical migration directory/policy;
- targeted persistence/model/migration verification.

Exact versions:
- Microsoft.EntityFrameworkCore 10.0.12
- Microsoft.EntityFrameworkCore.Relational 10.0.12
- Microsoft.EntityFrameworkCore.Design 10.0.12
- Npgsql.EntityFrameworkCore.PostgreSQL 10.0.3
- dotnet-ef 10.0.12

No domain schema or committed domain migration was introduced.

Verification:
- tested commit: `63734a34b1c86437504cc304cac1315b3d182ee1`
- workflow run: `35708370550`
- restore: PASS
- Release build: PASS — 0 warnings, 0 errors
- targeted tests: PASS — 11 / 11
- migration mechanism probe: PASS
- project-reference verification: PASS

Earlier verification failures were retained in canonical evidence and fixed in-scope.

Full Test Day pending.


## FW-IMP-004 — Audit/idempotency/outbox foundations
**Status:** COMPLETED

Implemented:
- persistence-neutral audit, durable idempotency and transactional outbox contracts in Mars.Application;
- Foundation EF Core records, mappings and stores in Mars.Infrastructure;
- minimum bounded/cancellation-aware outbox processor in Mars.Worker;
- first committed Foundation schema migration generated by EF Core.

Physical Foundation structures:
- `foundation.audit_events`
- `foundation.idempotency_operations`
- `foundation.outbox_messages`

Migration:
- `src/Mars.Infrastructure/Persistence/Migrations/Foundation/20260922095311_FwImp004FoundationPrimitives.cs`
- EF Core generated; no parallel/manual DDL architecture introduced.
- migration was not applied to a real PostgreSQL environment in this package.

Verification:
- tested commit: `06109051f530dff60774dc43d369986b343f4158`
- workflow run: `35713295141`
- restore: PASS
- Release build: PASS — 0 warnings, 0 errors
- targeted Foundation tests: PASS — 19 / 19
- committed migration scope/model-drift check: PASS
- project-reference verification: PASS

Boundaries preserved:
- no ERP domain table/business rule;
- no auth/identity provider selected;
- no OpenAPI tooling selected;
- no UI/deployment/provider integration;
- Valkey remains non-authoritative.

Failed intermediate CI checks and their corrections are recorded in:
- `docs/plan/01-foundation/fw-imp-004-implementation.md`

Full Test Day pending.


## FW-IMP-005 — API foundation implementation
**Status:** COMPLETED

Implemented:
- ASP.NET Core API host/pipeline on .NET 10;
- ASP.NET Core Identity + OpenIddict 7.7.1 authentication/protocol baseline;
- PostgreSQL/EF Core Identity/OpenIddict persistence in Foundation-owned `identity` schema;
- trusted authenticated-principal to Mars execution-context adaptation;
- server-side authorization hooks without ERP permission invention;
- deterministic Foundation error-to-HTTP mapping;
- Microsoft.AspNetCore.OpenApi 10.0.12 document generation;
- `/api/v1` Foundation-only protected proof route;
- separate liveness/readiness endpoints;
- targeted auth/API/OpenAPI/health/model verification.

Identity/protocol physical structures:
- `identity.users`
- `identity.user_claims`
- `identity.user_logins`
- `identity.user_tokens`
- `identity.OpenIddictApplications`
- `identity.OpenIddictAuthorizations`
- `identity.OpenIddictScopes`
- `identity.OpenIddictTokens`

Migration:
- `src/Mars.Infrastructure/Persistence/Migrations/Identity/20260922113226_FwImp005IdentityProtocol.cs`
- EF Core generated; no parallel manual DDL architecture introduced.
- migration scope is Foundation Identity/OpenIddict only; no ERP domain tables.
- final model-drift check reports no pending changes.
- no production migration was applied in this package.

Packages added:
- Microsoft.AspNetCore.OpenApi 10.0.12
- OpenIddict.AspNetCore 7.7.1
- Microsoft.AspNetCore.Identity.EntityFrameworkCore 10.0.12
- OpenIddict.EntityFrameworkCore 7.7.1

Verification:
- tested implementation commit: `22f31b087f887270bb43f4a39038d7c9a9e07b87`
- workflow run: `35722169157`
- .NET SDK: `10.0.401`
- runtime: `10.0.12`
- restore: PASS
- Release build: PASS — 0 warnings, 0 errors
- targeted Foundation tests: PASS — 26 / 26
- committed migration scope/model-drift check: PASS
- API liveness/readiness/protected-route smoke: PASS
- OpenAPI generation/expected `/api/v1` route: PASS
- Swagger UI absence: PASS
- project-reference verification: PASS

Boundaries preserved:
- ERP authorization/company/branch semantics remain Mars-owned;
- `Mars.Application` / `Mars.Domain` do not depend on OpenIddict persistence types;
- client request scope does not override trusted authenticated context;
- no password or implicit OAuth flow;
- no localStorage token baseline;
- no Swagger UI/Swashbuckle/NSwag/client generator;
- no ERP business endpoint/schema/rule;
- production signing/encryption secret storage remains deferred.

Failed intermediate CI checks and their corrections are recorded in:
- `docs/plan/01-foundation/fw-imp-005-implementation.md`

Full Test Day pending.



## FW-IMP-006 — Mars.Web + Mars.UI foundation
**Status:** COMPLETED

Implemented:
- Vite + TypeScript Mars.Web project;
- committed npm lockfile and Node 24 LTS CI baseline;
- Mars.Web shell and Mars-owned History API router;
- generic /api/v1 fetch client with same-origin cookie/session semantics, deterministic errors, correlation id and AbortSignal;
- semantic Mars.UI tokens;
- Button, Field, Dialog, Tabs, Lookup and Grid primitives;
- targeted browserless DOM/API/router/component tests;
- static checks preventing forbidden frontend frameworks, token storage, V38 source coupling and unsafe innerHTML.

Exact frontend versions:
- Vite 8.3.0
- TypeScript 7.0.2
- tsx 4.23.15
- happy-dom 20.14.5
- Node.js 24.21.0
- npm 11.19.0

Verification:
- tested implementation commit: `1264981655329099a086c7048c0890caf04dc9f2`
- workflow run: `35729371845`
- npm ci: PASS
- TypeScript: PASS
- targeted frontend tests: PASS — 10 / 10
- Vite production build: PASS
- static architecture checks: PASS
- .NET Release build: PASS — 0 warnings, 0 errors
- existing Foundation tests: PASS — 26 / 26
- migration/model drift: PASS
- API smoke/OpenAPI: PASS
- project references: PASS

Boundaries preserved:
- no React/Vue/Angular/Bootstrap/Tailwind/jQuery;
- no localStorage/sessionStorage token baseline;
- no ERP business screen/rule;
- no Desktop/Mobile shell selection;
- no deployment implementation;
- V38 product behavior adapted without copying its single-file patch architecture.

Failed intermediate CI checks and corrections:
- `docs/plan/01-foundation/fw-imp-006-implementation.md`

Full Test Day pending.


## FW-IMP-007 — Docker/test deployment baseline
**Status:** COMPLETED

Implemented:
- API, migrator and Web Docker images;
- TEST-only Foundation Docker Compose project;
- preservation of existing PostgreSQL/Valkey Compose ownership;
- separate TEST migration/runtime PostgreSQL roles;
- migration-before-startup policy;
- readiness failure on pending migrations;
- remote TEST preflight/deployment/readiness/smoke automation;
- TEST-only static/proxy Web serving for Vite production assets.

Evidence:
- canonical report: `docs/plan/01-foundation/fw-imp-007-implementation.md`
- tested implementation commit: `2821c98bbd04b81dcbb10e99ddb0a4974ca7a517`
- successful workflow run: `35865600657`
- remote TEST deployment: PASS
- /health/live: 200
- /health/ready: 200
- protected API unauthenticated: 401
- OpenAPI: 200
- runner-to-TEST smoke: PASS

Not selected:
- production deployment;
- production secret store;
- production ingress/reverse proxy/TLS;
- structured logging/metrics backend;
- Desktop/Mobile shell technology.

Full Test Day remains pending.


## FW-IMP-008 — Thin vertical framework proof
**Status:** COMPLETED

Implemented:
- non-domain Foundation proof handler and persistence boundary;
- company-scoped durable idempotency;
- audit + outbox write-set in one PostgreSQL transaction;
- protected `POST /api/v1/foundation/proof`;
- Foundation-only Mars.Web `/proof` route;
- targeted .NET/Web proof tests;
- expanded TEST deploy/smoke verification.

Evidence:
- canonical report: `docs/plan/01-foundation/fw-imp-008-implementation.md`
- final tested implementation commit: `bf9b3882dbc91ee801d169a62587c7658baaa63b`
- Foundation Build run: `35868005913` — SUCCESS
- Foundation Test Deploy run: `35868151531` — SUCCESS
- frontend tests: 11 / 11 PASS
- Foundation tests: 30 / 30 PASS
- TEST /proof: 200
- TEST live/ready: 200 / 200
- unauthenticated proof POST: 401
- runner-to-TEST smoke: PASS

No new ERP schema, migration, business rule, package, production deployment or authentication bypass was introduced.

P4 Foundation implementation exit is satisfied. Next phase is P5 Core application implementation, starting with Parties after the first implementation work package is explicitly defined.

# PRODUCT-IMP-001 — Product Master Completion Tranche Readiness

Status: READY FOR IMPLEMENTATION

## Owner direction

The owner explicitly requires broad coherent implementation tranches instead of one work package per small capability.

PRODUCT-IMP-001 therefore groups the source-backed Product master capabilities frozen by PLAN-004 and PLAN-010 into one implementation package.

Internal sequencing and multiple commits are allowed. Do not create separate PRODUCT-IMP package IDs merely for Product, Variant, UOM, Barcode, Category or External Mapping implementation convenience.

## Sources

Governance:
- `docs/plan/ai-cmd.md`
- `docs/ai/README.md`
- `docs/ai/skill-router.md`

Frozen Product contracts:
- `docs/plan/04-urun-stok/README.md`
- `docs/plan/04-urun-stok/plan.md`
- `docs/plan/04-urun-stok/workflows.md`
- `docs/plan/04-urun-stok/forms.md`
- `docs/plan/04-urun-stok/data-contract.md`
- `docs/plan/04-urun-stok/permissions.md`
- `docs/plan/04-urun-stok/integrations.md`
- `docs/plan/04-urun-stok/reports.md`
- `docs/plan/04-urun-stok/acceptance-criteria.md`

Database contracts:
- `docs/db/00-domain-dictionary.md`
- `docs/db/01-design-principles.md`
- `docs/db/02-module-ownership.md`
- `docs/db/03-entity-catalog.md`
- `docs/db/04-relationships.md`
- `docs/db/07-constraints-and-concurrency.md`
- `docs/db/08-index-access-patterns.md`
- `docs/db/09-migration-conventions.md`

Repository baseline:
- no Product Domain/Application/Persistence/API/Web implementation exists at scope freeze;
- no Product migration exists;
- committed migration baseline is 6.

## ACTIVE SKILLS

Primary:
- erp-domain-specialist — protects Product/Variant/UOM/barcode semantics and master-vs-Inventory authority.
- database-architect — owns normalized physical design, deterministic uniqueness, stale-write protection and additive migration.

Reviewers:
- software-architect — modular-monolith/application/API boundary.
- software-developer — implementation consistency and server-side invariants.
- software-test-engineer — targeted duplicate/stale/company/lifecycle evidence.
- accounting-finance-specialist — vetoes Product cost/value authority leakage.
- warehouse-operations-shipping-specialist — vetoes physical stock/reservation/lot/serial authority leakage.
- ux-ui-specialist — Product directory/detail workflow.
- web-design-specialist — Mars.UI/Web implementation continuity.

## Objective

Deliver a usable Product master in one broad tranche while preserving Inventory Ledger, Warehouse operations and Finance/Costing as separate future authorities.

## Included capability set

### Product core

Implement company-scoped Product master with:
- public UUID;
- caller-supplied canonical Product Code;
- name;
- optional description;
- kind GOODS / SERVICE;
- explicit SELLABLE / PURCHASABLE / STOCKABLE flags;
- tracking strategy NONE / LOT / SERIAL / LOT_SERIAL;
- ACTIVE / INACTIVE;
- optimistic version;
- exactly one initial Base UOM relation;
- audit + durable PostgreSQL idempotency.

Rules:
- SERVICE + STOCKABLE is rejected;
- non-STOCKABLE Product uses tracking NONE;
- Product Code is deterministic and company-scoped;
- code format/series remains Settings/Numbering-owned;
- no Product stock/balance/value/cost authority is introduced.

### Product read/detail/edit

Implement:
- Product directory/list/search;
- Product detail;
- edit Product Code/name/description and SELLABLE/PURCHASABLE flags with expected-version protection;
- do not change kind, STOCKABLE, tracking strategy or Base UOM through ordinary edit in this tranche.

The excluded high-risk fields require future operational-use checks once Inventory/Reservation/open physical processes exist.

### Product lifecycle

Implement:
- ACTIVE → INACTIVE with `product.deactivate`;
- INACTIVE → ACTIVE with `product.reactivate`;
- expected-version protection;
- audit/idempotency;
- reactivation reruns Product Code and active child identity uniqueness/validity available inside Product authority;
- lifecycle never zeros stock, cancels documents or rewrites history.

Inventory/Warehouse workflows may later continue to resolve inactive identities for physical cleanup.

### UOM master and Product-UOM

Implement company-compatible controlled UOM definitions and Product-UOM assignments.

Include:
- UOM code/name;
- UOM ACTIVE/INACTIVE;
- Product-level initial Base UOM;
- alternate Product/Variant UOM assignment;
- deterministic orientation `1 alternate = factor × base`;
- positive conversion factor;
- Product-UOM ACTIVE/INACTIVE;
- update alternate conversion for future use only;
- no duplicate active Product/Variant/UOM mapping;
- exactly one active Product Base UOM.

Physical implementation decision:
- conversion factor uses PostgreSQL `numeric(28,9)` / .NET `decimal`;
- this is storage precision for the conversion factor, not a new transaction quantity fraction policy;
- future UOM-specific transaction quantity scale/fraction policy remains outside this package.

Permissions:
- `product.uom.read`
- `product.uom.manage`

Explicitly exclude Base UOM replacement after creation:
- `product.uom.change_base` permission exists in frozen namespace, but no Base UOM change endpoint is implemented by PRODUCT-IMP-001;
- exact safe replacement procedure remains dependent on operational history.

### Variant

Implement optional company-compatible Product child identity:
- public UUID;
- optional distinct Variant/SKU Code;
- name/differentiator label;
- optional tracking strategy override only for STOCKABLE Product;
- ACTIVE/INACTIVE;
- optimistic version;
- create/edit/lifecycle;
- no arbitrary attribute/EAV taxonomy.

Permissions:
- `product.variant.read`
- `product.variant.manage`

### Barcode Mapping

Implement:
- namespace/type;
- normalized trimmed value;
- Product or Variant target;
- optional Product-UOM/package assignment;
- ACTIVE/INACTIVE;
- public UUID/version;
- one active company + namespace + normalized value resolves to one mapping;
- deactivate/reactivate lifecycle;
- no GS1/provider checksum/enrollment verification claim.

Permissions:
- `product.barcode.read`
- `product.barcode.manage`

### Category / classification

Implement normalized company-context Category master and Product relationships:
- Category code;
- name;
- optional parent;
- parent-cycle prevention;
- Product many-to-many assignments;
- optional one primary Category per Product;
- create/edit;
- assign/unassign Product;
- no free-text/comma-separated/EAV classification.

Permissions:
- `product.category.read`
- `product.category.manage`

### Product External Mapping

Implement generic Product/Variant mapping:
- system/provider/source code;
- normalized optional account scope;
- external ID/SKU;
- ACTIVE/INACTIVE;
- deterministic company + system + account-scope + external-identity uniqueness;
- public UUID/version;
- retry-safe create/state change.

PLAN-004 defines no dedicated External Mapping permission code. To avoid inventing a new permission namespace:
- detail/read follows `product.read`;
- mutation follows `product.edit`.

Provider-specific fields/synchronization are excluded.

### API / Web

Protected `/api/v1/products` surfaces will cover included Product master capabilities.

Mars.Web adds Product directory/detail/create/manage flow using existing Mars.UI primitives.

No editable stock quantity/value field is permitted.

No read-only stock projection is shown until Inventory authority exists.

## Explicit exclusions

PRODUCT-IMP-001 does NOT implement:
- Warehouse master;
- Location master;
- Inventory disposition;
- Inventory Ledger;
- Reservation;
- Lot/Serial records or physical movement;
- current stock/available quantity authority;
- current inventory valuation/average cost/COGS;
- Base UOM replacement;
- post-use STOCKABLE capability changes;
- post-use tracking-strategy changes;
- generic Variant attribute/EAV schema;
- exact Product/SKU numbering generator;
- provider/marketplace synchronization;
- GS1/provider verification;
- product files/media/technical attributes;
- export;
- production deployment;
- Full Test Day.

## High-risk master-change rule

Ordinary Product edit does not expose:
- kind change;
- STOCKABLE change;
- tracking-strategy change;
- Base UOM replacement.

These are intentionally fail-closed until the required Inventory/Reservation/open-process authority exists and a safe transition procedure can evaluate it.

This prevents Product master from pretending that no physical dependency exists.

## Database boundary

Expected new normalized Product-owned structures:
- UOM;
- Product;
- Variant;
- Product UOM;
- Barcode Mapping;
- Category;
- Product Category;
- Product External Mapping.

Requirements:
- PostgreSQL authority;
- schema `products`;
- relational/3NF structures;
- BIGINT internal IDs;
- UUID public IDs for externally/API-addressed records;
- company-compatible FKs;
- optimistic version on mutable master records;
- deterministic company/code and active barcode/external mapping constraints;
- history-preserving lifecycle;
- no JSON/EAV escape;
- no stock/value authority;
- additive EF migration only.

## Acceptance evidence

Normal development:
- Product domain/application/persistence targeted tests;
- company isolation;
- permission checks;
- Product/Variant code duplicate conflicts;
- SERVICE/STOCKABLE invariant;
- UOM positive/deterministic conversion;
- duplicate Product-UOM conflict;
- barcode ambiguity conflict;
- Category cycle/primary constraints;
- External Mapping duplicate conflict;
- lifecycle/stale/idempotency checks;
- frontend type/test/build;
- .NET Release build;
- generated additive migration;
- migration safety;
- EF pending-model clean;
- TEST migration/deployment/smoke;
- unauthenticated protected Product route checks.

Do not claim authenticated TEST Product mutation unless direct evidence exists.

Heavy concurrency, broad authenticated browser E2E, permission/IDOR matrix, high-volume lookup, performance/load and backup/restore remain Full Test Day.

## SOURCE / INFERENCE / UNKNOWN / BLOCKED

SOURCE:
- PLAN-004 freezes Product/Variant/UOM/barcode/category/external-mapping ownership and lifecycle.
- PLAN-010 freezes logical Product entities, company compatibility, deterministic identity constraints, optimistic concurrency and migration discipline.
- owner requires broad coherent implementation tranches.

INFERENCE / implementation decisions:
- UOM definitions are kept company-compatible so Product-UOM ownership cannot cross company boundaries.
- conversion factor physical type is `numeric(28,9)`.
- generic Product External Mapping mutation uses `product.edit` because PLAN-004 defines the mapping as Product authority but no dedicated mapping permission code.

UNKNOWN / deferred:
- UOM-specific transaction quantity scale/fraction policy;
- provider-specific synchronization/verification;
- generic Variant attribute taxonomy;
- safe Base UOM replacement after operational history;
- safe STOCKABLE/tracking change after Inventory/Reservation/open physical use.

BLOCKED:
- only the explicitly excluded high-risk/provider/inventory semantics.
- PRODUCT-IMP-001 itself is not blocked.

## Decision

Assigned:
`PRODUCT-IMP-001 — Product Master Completion Tranche`

Status:
`READY FOR IMPLEMENTATION`

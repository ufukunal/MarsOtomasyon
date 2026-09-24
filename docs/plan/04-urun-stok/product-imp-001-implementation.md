# PRODUCT-IMP-001 — Product Master Completion Tranche

Status: COMPLETED
Date: 2026-09-24

## Scope

Implemented as one broad Product master tranche:
- Product create/list/detail/read/edit and ACTIVE/INACTIVE lifecycle
- UOM master and initial Base UOM
- alternate Product/Variant UOM assignment and conversion lifecycle
- optional Variant create/edit/lifecycle
- Barcode Mapping create/read/lifecycle
- normalized Category hierarchy and Product assignments
- Product External Mapping
- permissions, protected API, Mars.Web, audit, durable PostgreSQL idempotency and optimistic concurrency
- additive EF migration

Explicitly excluded:
- Inventory Ledger / physical stock authority
- Warehouse/Location operational workflows
- Reservation and Lot/Serial physical authority
- inventory valuation/current cost/COGS
- Base UOM replacement
- post-use STOCKABLE/tracking-strategy transitions
- generic Variant EAV/attribute taxonomy
- provider/marketplace sync and GS1/provider verification
- Product numbering generator
- production deployment
- Full Test Day

Canonical readiness:
docs/plan/04-urun-stok/p5-product-master-completion-readiness.md

## Commits

Scope freeze:
c90c8372b90990f677262c274fe5281a7fac121b

Implementation:
817dfea81acbf1e93586b030412bd492f8a3ba20

Verification fix:
962a18e776580f1a207853e7936d6c5d093a897e

Generated migration:
9b0731792a8641b632c612b7abb6f74799dd2aa4

Verification infrastructure:
77eb64d754dcab37e837fbacb3ba47cb7b130b63
f8a6939cae8045a11211baf24fd8fc35fc4a46cc
ac92911a8f5fcda070622b084216a43a70ad7d77

Final runtime-tested SHA:
ac92911a8f5fcda070622b084216a43a70ad7d77

## Product invariants

- Product is company-scoped.
- Product Code is deterministic company-scoped identity.
- Product kind is GOODS or SERVICE.
- SERVICE cannot be STOCKABLE.
- Non-STOCKABLE Product uses NONE tracking.
- SELLABLE / PURCHASABLE / STOCKABLE are explicit.
- Product master stores no current stock, stock balance, valuation or COGS truth.
- Ordinary edit does not change kind, STOCKABLE, tracking strategy or Base UOM.
- Lifecycle does not rewrite history or create Warehouse/Finance effects.

## UOM / Variant / Barcode / Category / External Mapping

Implemented:
- company-compatible UOM master
- one active Base UOM per Product
- positive alternate conversion factor using PostgreSQL numeric(28,9)
- optional Variant with optional company-scoped code and lifecycle
- active barcode company + namespace + value uniqueness
- normalized Category parent hierarchy with cycle prevention
- Product Category assignment with at most one primary Category
- Product External Mapping active scoped uniqueness
- optimistic versioning on mutable Product master records

## Database

Generated migration:
src/Mars.Infrastructure/Persistence/Migrations/Products/20260924085617_ProductImp001ProductMasterCompletion.cs

New normalized tables:
- products.products
- products.uoms
- products.variants
- products.product_uoms
- products.barcodes
- products.categories
- products.product_categories
- products.external_mappings

Migration count:
- before: 6
- after: 7

EF pending-model:
PASS — no changes since last migration.

## API / Web

Protected /api/v1/products surfaces include:
- Product list/create/detail/edit
- deactivate/reactivate
- UOM list/create/state
- Category list/create/edit
- Variant create/edit/state
- Product-UOM add/update/state
- Barcode create/state
- Product Category assign/unassign
- Product External Mapping create/state

Mars.Web:
- /products

No editable stock quantity or inventory value/cost field is introduced.

## Final verification

Foundation Build:
- run 35981641268
- job 107574669148
- SUCCESS
- frontend 19 / 19 PASS
- Release build PASS
- 0 warnings / 0 errors
- Foundation targeted tests 66 / 66 PASS
- EF pending-model PASS

Foundation Test Deploy:
- run 35981641137
- job 107574851341
- SUCCESS
- migration safety count 7
- pre-deploy TEST migration count 6
- migration apply PASS
- deployed migration count 7
- remote TEST preflight PASS
- API / migrator / Web build PASS
- runtime grants PASS
- health gate PASS
- /products 200
- /parties 200
- /health/live 200
- /health/ready 200
- protected Product routes unauthenticated 401
- OpenAPI 200
- smoke PASS

A real authenticated TEST Product mutation is not claimed.

## Verification history

On 962a18e:
- Product code built
- frontend 19 / 19 passed
- Product-specific tests passed
- Product migration generation succeeded

Build/Test Deploy still failed because the inherited PARTY-IMP-006 EF test searched for the unqualified table name external_mappings. Product introduced products.external_mappings alongside parties.external_mappings, making the old test ambiguous.

This was a verification regression, not a Product domain conflict.

Final corrections:
- TEST migration safety includes Migrations/Products
- migration generator is frozen as manual/read-only after migration commit
- inherited Party EF test is schema-qualified

Final Build and Test Deploy on ac92911 both succeeded.

## Deferred

Still deferred:
- Base UOM replacement after operational history
- post-use STOCKABLE/tracking transitions
- UOM transaction fraction/scale policy
- generic Variant EAV taxonomy
- provider/marketplace/GS1 behavior
- Inventory physical authority
- Warehouse operations
- Finance/Costing valuation

## Completion

PRODUCT-IMP-001 is COMPLETED.

Planning metrics are unchanged:
- active state master: 9 / 30 = 30.0%
- P2: 8 / 8 = 100.0%

The separate docs/db/acceptance-criteria.md value 10 / 30 = 33.3% remains an explicit repository inconsistency and was not silently normalized.

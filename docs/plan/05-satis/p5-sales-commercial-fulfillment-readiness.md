# SALES-IMP-001 — Sales Commercial & Fulfillment Authority Tranche Readiness

Status: READY FOR IMPLEMENTATION

## Owner direction

The owner requires broad coherent implementation tranches.

SALES-IMP-001 therefore groups the source-backed Sales commercial chain that can be implemented against the already completed Party, Product and Inventory authorities without stealing Finance or Warehouse operational ownership.

Internal sequencing and multiple commits are allowed.

Do not split Quote, Order, Reservation integration, Dispatch and pre-post Invoice commercial authority into separate SALES-IMP package IDs merely for implementation convenience.

A later Finance-dependent Sales completion tranche is allowed only because Sales Invoice POST/REVERSE requires Finance ledger/value authority that does not exist yet.

## Reconciled repository baseline

Repository: ufukunal/MarsOtomasyon
Branch: main
Scope-freeze HEAD: 7422aea2f5f750b53187578992fe64fdc5e4f7d4

Completed predecessors:
- PARTY-IMP-001…006
- PRODUCT-IMP-001
- INVENTORY-IMP-001

Inventory canonical implementation:
- docs/plan/04-urun-stok/inventory-imp-001-implementation.md

Latest runtime-tested SHA:
- edcc24f1200b70aad102fc510ad7bec60c4515e7

Inventory verification:
- Foundation Build 35998315605 — SUCCESS
- Foundation Test Deploy 35998315582 — SUCCESS
- committed/deployed migration count: 8

Current source baseline at scope freeze:
- no Sales Domain implementation;
- no Sales Application implementation;
- no Sales persistence/configuration;
- no Sales API endpoints;
- no Sales Mars.Web route;
- no Migrations/Sales directory;
- no Finance Account Ledger/Valuation implementation;
- no Settings/Sales Commercial Policy implementation;
- no Warehouse operational Pick/Pack/Stage/Load implementation.

## Sources

Governance/state:
- docs/plan/ai-cmd.md
- docs/ai/README.md
- docs/ai/skill-router.md
- docs/plan/active-task.yaml
- docs/plan/project-state.yaml
- docs/plan/handoff/current.md
- docs/plan/tasks/active.md
- docs/plan/tasks/backlog.md
- docs/plan/tasks/completed.md
- docs/plan/master-project-plan.md

Frozen Sales:
- docs/plan/05-satis/README.md
- docs/plan/05-satis/plan.md
- docs/plan/05-satis/workflows.md
- docs/plan/05-satis/forms.md
- docs/plan/05-satis/data-contract.md
- docs/plan/05-satis/permissions.md
- docs/plan/05-satis/integrations.md
- docs/plan/05-satis/reports.md
- docs/plan/05-satis/acceptance-criteria.md

Warehouse boundary:
- docs/plan/06-ambar-depo/plan.md
- docs/plan/06-ambar-depo/workflows.md
- docs/plan/06-ambar-depo/permissions.md

Logical database:
- docs/db/02-module-ownership.md
- docs/db/03-entity-catalog.md
- docs/db/04-relationships.md
- docs/db/05-ledgers-and-finance.md
- docs/db/06-snapshots-and-projections.md
- docs/db/07-constraints-and-concurrency.md
- docs/db/08-index-access-patterns.md
- docs/db/09-migration-conventions.md
- docs/db/acceptance-criteria.md

Current implementation dependencies:
- src/Mars.Application/Inventory/InventoryAuthority.cs
- src/Mars.Application/Inventory/InventoryPermissions.cs
- src/Mars.Infrastructure/Persistence/Inventory/EfInventoryPersistence.cs
- current Party/Product persistence
- current MarsDbContext and Actions

## ACTIVE SKILLS

Primary:
- erp-domain-specialist — owns Quote/Order/Dispatch/Invoice effect semantics, source-target quantities, lifecycle and reversal.
- accounting-finance-specialist — protects receivable/COGS/settlement boundaries and prevents premature Finance authority inside Sales.

Reviewers:
- database-architect — normalized document/version/source-link schema, source caps, constraints, migration and transaction locking.
- software-architect — cross-module transaction composition, no duplicate Inventory/Finance authority.
- software-developer — implementation consistency and reuse of current Party/Product/Inventory contracts.
- software-test-engineer — source-cap, stale-state, idempotency, permission, company and no-double-post evidence.
- security-specialist — permission/SoD/trusted company and Warehouse execution scope.
- ux-ui-specialist — document state/source/remaining/approval UX.
- web-design-specialist — Mars.Web/Mars.UI continuity.

## Frozen authority model

Sales owns:
- Quote/revisions/lines;
- Quote conversion links;
- Sales Order/effective versions/lines/amendments;
- Sales-owned commercial source-target links;
- Dispatch commercial document/lines/state;
- Sales Invoice commercial/tax draft/source records and later posted commercial snapshot;
- Proforma if implemented in this tranche;
- Sales commercial document projections.

Inventory owns:
- Reservation authority;
- physical stock quantity;
- Warehouse/Location/disposition/Lot/Serial position;
- physical Inventory Ledger.

Warehouse later owns:
- Pick/Pack/Stage/Load work;
- operational allocation/recommendation/execution;
- offline/mobile Warehouse work.

Finance owns:
- Account Ledger;
- receivable monetary posting;
- Inventory Valuation/Dispatch Cost Bridge/COGS;
- Collection;
- settlement;
- customer balance;
- FX carrying/revaluation.

Returns owns return physical/financial workflow.

## Objective

Deliver a broad Sales commercial and fulfillment authority that supports:

Quote
→ partial/repeated conversion
→ Sales Order
→ explicit Inventory Reservation
→ Dispatch POST/reversal

and prepares the Sales-owned Invoice commercial/source/calculation draft model without pretending that Finance posting exists.

## Included capability set

### 1. Sales permissions

Freeze Sales permission codes from PLAN-002 except superseded Warehouse operational ownership.

Quote:
- sales.quote.read
- sales.quote.create
- sales.quote.edit_draft
- sales.quote.revise
- sales.quote.submit_approval
- sales.quote.approve
- sales.quote.send_customer
- sales.quote.convert_order
- sales.quote.cancel
- sales.quote.export

Order:
- sales.order.read
- sales.order.create
- sales.order.edit_draft
- sales.order.submit_approval
- sales.order.approve
- sales.order.confirm
- sales.order.amend
- sales.order.activate_amendment
- sales.order.hold
- sales.order.release_hold
- sales.order.cancel_remaining
- sales.order.close
- sales.order.export

Dispatch:
- sales.dispatch.read
- sales.dispatch.create
- sales.dispatch.edit_draft
- sales.dispatch.post
- sales.dispatch.handoff
- sales.dispatch.reverse
- sales.dispatch.export

Do not use PLAN-002 sales.dispatch.pick / sales.dispatch.pack as operational authority.
Later PLAN-006 warehouse.pick.* / warehouse.pack.* / warehouse.stage.* / warehouse.load.* owns those actions.

Invoice pre-post:
- sales.invoice.read
- sales.invoice.create
- sales.invoice.edit_draft
- sales.invoice.export

Reserve for later Finance-integrated tranche:
- sales.invoice.post
- sales.invoice.reverse
- sales.invoice.fx_override
- sales.invoice.send_edocument

Inventory Reservation mutation permission names introduced for Sales integration:
- inventory.reservation.create
- inventory.reservation.increase
- inventory.reservation.release

Reservation consume remains an internal Dispatch posting effect, not a general user action.

### 2. Party/Product eligibility

Sales create/confirm commands validate server-side:
- Party exists in trusted company;
- Party is ACTIVE;
- CUSTOMER role is ACTIVE;
- Product/Variant belongs trusted company;
- Product is ACTIVE and SELLABLE;
- UOM is an eligible active Product-UOM;
- historical snapshots remain readable after later master changes.

Dispatch additionally relies on Inventory for STOCKABLE/tracking/physical-position validation.

No Party balance or Product stock field is copied into Sales authority.

### 3. Quote authority

Implement:
- company-scoped public UUID;
- caller-supplied document number until Settings/Numbering allocator exists;
- Party/customer source;
- currency/commercial terms;
- revision sequence;
- lines with Product/Variant/UOM and commercial snapshot values;
- DRAFT;
- optional approval path;
- CUSTOMER_REVIEW;
- ACCEPTED;
- PARTIALLY_CONVERTED;
- CONVERTED;
- SUPERSEDED;
- EXPIRED;
- CANCELLED;
- create/read/edit-draft/revise/cancel;
- exact revision history;
- audit/idempotency/concurrency.

Rules:
- Quote has no Reservation/STOCK/ACCOUNT/CASH-BANK effect.
- externally/customer-reviewed history is not overwritten.
- revision creates new history.
- accepted/superseded history is immutable except explicit state transition.
- conversion always references exact revision + line + quantity.

### 4. Approval evidence and fail-closed policy absence

PLAN-010 defines Foundation Approval Decision but current source has no implementation.

SALES-IMP-001 includes the minimal shared approval evidence primitive required by Sales:
- actor;
- company;
- document public identity;
- document kind;
- exact revision/version/amendment sequence;
- submitted snapshot identity/hash or equivalent immutable reference;
- reason codes;
- submitter;
- approver;
- timestamps;
- decision;
- correlation.

SoD:
- creator/submitter cannot approve own approval-required document/amendment;
- material mutation invalidates/requires new approval.

Sales Commercial Policy/Settings is not implemented and its exact pricing/tolerance model is not frozen physically.

Fail-closed tranche behavior:
- no client flag may assert that a document is “within policy”;
- while no authoritative Sales Commercial Policy evaluator exists, Quote acceptance and direct/source-less Order confirmation follow the approval-required path;
- an unchanged Order created from an already accepted/approved Quote may confirm without a second approval;
- a confirmed-order amendment that increases exposure or changes commercial terms requires approval;
- purely operational future-scope metadata amendment does not require approval unless another frozen rule does.

This is a conservative implementation of PLAN-002’s “missing applicable policy => manual deviation requires approval” rule and must be superseded by an authoritative Sales Commercial Policy evaluator when Settings/configuration is implemented.

### 5. Quote → Order conversion

Implement quantity-bearing conversion links:
- exact Quote public id;
- revision;
- source Quote line;
- target Order public id;
- target effective version/line;
- converted quantity.

Rules:
- repeated partial conversion allowed;
- cumulative net converted quantity cannot exceed effective offered quantity;
- source cap is protected in PostgreSQL transaction, not UI only;
- all-zero remaining conversion sets derived Quote progress to CONVERTED;
- partial remainder remains visible;
- conversion creates no Reservation or stock effect.

### 6. Sales Order authority

Implement:
- company-scoped public UUID;
- caller-supplied document number;
- customer/source Quote relation where applicable;
- effective version;
- lines;
- commercial/Product/UOM snapshots;
- DRAFT;
- optional PENDING_APPROVAL;
- CONFIRMED;
- ON_HOLD;
- PARTIALLY_COMPLETED;
- COMPLETED;
- CANCELLED_REMAINDER;
- pre-processing CANCELLED;
- create/read/edit-draft/confirm/hold/release/cancel-remainder/close;
- audit/idempotency/stale-write protection.

Rules:
- confirmation is DOC only;
- no stock-out;
- no receivable;
- no automatic Reservation;
- ordered quantity authority is effective Order version history;
- reserved/shipped/invoiced values are derived from owning authorities.

### 7. Controlled Order amendment

Implement append/version-oriented amendment:
- prior effective version;
- amendment sequence;
- reason;
- proposed delta;
- before/after;
- actor/time;
- approval evidence where required;
- activation result.

Allowed:
- add line;
- increase quantity;
- decrease/cancel only unprocessed remainder;
- delivery/contact/notes change;
- future-scope commercial-term change.

Rules:
- processed Product/UOM identity is immutable;
- quantity cannot fall below max(net shipped, net posted-invoiced when Finance-integrated Invoice posting exists);
- current tranche always protects shipped floor immediately;
- Invoice-processed floor becomes active when posted Invoice authority exists;
- active Reservation above amended eligible remainder must be explicitly released through Inventory before activation;
- quantity increase never auto-reserves;
- stale version conflicts;
- activation is atomic with required Reservation release.

### 8. Manual Reservation integration

Expose Sales-context actions only from eligible confirmed Order/version/line.

Sales command invokes existing Inventory Reservation authority.

Create/increase:
- explicit user action only;
- exact Order/version/line;
- Product/Variant/UOM;
- Warehouse;
- positive quantity;
- Inventory validates available-to-reserve.

Release:
- explicit authorized user action;
- amendment/cancel workflows may require release before their Sales state transition;
- release and Sales transition compose in one PostgreSQL transaction.

Sales persists only source/reference linkage/projection data needed for navigation.
Inventory Reservation remains authoritative.

No Sales reserved-total authority is introduced.

### 9. Warehouse execution scope for Dispatch

PLAN-006 requires Sales Dispatch POST permission plus required Warehouse scope.

Existing ADR-0005 only models Actor + Company + PermissionCode.

SALES-IMP-001 introduces an Inventory/Warehouse-owned resource-scope authority rather than putting Inventory FK semantics into Foundation Permission Grant.

Logical Warehouse Access Grant:
- ActorId;
- CompanyId;
- WarehousePublicId;
- active/revoked state;
- grant/revoke audit metadata;
- deterministic uniqueness.

Application contract:
- IWarehouseScopeEvaluator or equivalent.

Dispatch state-changing actions validate:
1. required Sales permission;
2. trusted Company;
3. exact Warehouse access scope.

No role/group administration UI is invented.
Controlled test/bootstrap grants are permitted for verification.
A later Security/Settings administration workflow may manage these records.

### 10. Dispatch commercial authority

Implement:
- company-scoped public UUID;
- caller-supplied document number;
- exact source Order effective version/line;
- line quantity;
- UOM snapshot;
- Warehouse;
- optional linked Reservation;
- immutable posting result links to Inventory movement(s);
- lifecycle:
  - DRAFT
  - READY
  - POSTED
  - HANDED_OVER
  - DELIVERED
  - CANCELLED pre-post
  - REVERSED post correction

PICKING/PACKING/STAGING/LOADING remain later Warehouse work states and are not implemented as Sales authority.

A direct Sales POST path may use explicit eligible Inventory physical source positions when no Warehouse work exists.
This selection is only posting input/snapshot; it is not a Pick/Pack work authority.

Rules:
- cumulative posted net Dispatch quantity <= effective Order remaining-to-ship;
- cross-company/source-version mismatch blocked;
- only AVAILABLE eligible physical source may be used for normal Sales Dispatch;
- tracking/lot/serial/negative-stock invariants remain Inventory-owned;
- current physical availability is revalidated at POST;
- no stock effect before POST.

### 11. Dispatch POST transaction

One PostgreSQL transaction must atomically:
- lock/revalidate Sales source Order/version/line remaining;
- transition Dispatch to POSTED;
- call Inventory physical authority for exact STOCK OUT movement(s);
- consume linked Reservation quantity where applicable;
- persist Inventory movement public-id links;
- audit/idempotency/outbox records.

Existing Inventory persistence already joins an ambient MarsDbContext transaction when one exists.
Do not create a second stock implementation.

Sub-operation idempotency keys must be deterministic under one Dispatch operation identity.

### 12. Dispatch reversal

Implement explicit linked reversal:
- original Dispatch remains immutable;
- new reversal state/evidence references original;
- Inventory creates linked compensating physical movement(s);
- Dispatch net shipped projection decreases accordingly.

B004 manual Reservation rule remains in force:
- reversal does not silently create a new Reservation;
- if the business wants commitment restored, an explicit authorized Reservation action is required after reversal/re-evaluation.

Downstream posted Invoice dependency is not present in this tranche; when Finance-integrated Invoice POST exists, Dispatch reversal must block or use a compensation chain according to exact downstream state.

### 13. Dispatch post-operational states

HANDED_OVER and DELIVERED are Sales commercial/logistics states with:
- no stock effect;
- no additional Reservation effect;
- audit/version protection.

Carrier/provider integration is not required for the local state model.

### 14. Sales Invoice pre-post commercial authority

Implement Sales-owned Invoice preparation without pretending Finance posting exists.

Included:
- Invoice header/lines;
- company/customer;
- source mode:
  - Dispatch
  - Order
  - Direct
- exact source links and quantities;
- Product/Variant/UOM snapshots;
- Party legal/tax/address snapshot fields;
- KDV-exclusive unit price;
- line discount;
- document discount allocation data;
- taxable base;
- tax rate/treatment;
- line tax;
- net/tax/gross totals;
- transaction currency fields;
- date/due date;
- DRAFT;
- CANCELLED before POST;
- read/create/edit-draft/cancel;
- source-eligibility preview;
- audit/idempotency/version.

No Invoice POSTED state is externally reachable in SALES-IMP-001.

Reason:
- PLAN-002 requires Invoice POST to atomically create Finance Account receivable and financial COGS;
- no Finance Account Ledger, valuation pool or Dispatch Cost Bridge implementation exists.

Therefore:
- sales.invoice.post is fail-closed/unexposed;
- sales.invoice.reverse is fail-closed/unexposed;
- e-document send is not implemented;
- no Invoice can claim posted accounting truth.

This is a dependency boundary, not a change to PLAN-002.

### 15. Calculation scope

Implement deterministic decimal calculation primitives needed by Quote/Order/Invoice draft:
1. KDV-exclusive unit price;
2. line discount;
3. proportional document-discount allocation;
4. taxable base;
5. per-line KDV;
6. document totals as sum of rounded line results;
7. stable residual allocation rule;
8. midpoint away from zero;
9. no hidden balancing.

TRY:
- authoritative minor unit = 2 from frozen PLAN-002.

Non-TRY:
- exact configured/ISO minor-unit authority is not implemented in repository;
- non-TRY finalization cannot claim authoritative minor-unit/FX behavior in this tranche;
- schema/contracts may carry currency code, but commands requiring final authoritative rounding for a non-TRY document fail closed until server-side currency minor-unit policy exists.

Invoice FX:
- TCMB default/manual override is part of later Finance-integrated Invoice POST tranche because no provider/config authority currently exists.
- do not fabricate FX rates.

### 16. Proforma

Proforma is optional but source-backed and may be included inside SALES-IMP-001 without a new package ID:
- sourced from Quote/Order;
- informational;
- no Reservation/STOCK/ACCOUNT/CASH-BANK/COGS effect;
- read/create/cancel/export;
- no posting semantics.

If omitted from initial implementation for effort reasons, it remains within the same SALES-IMP-001 package and must be completed before closing that package.

### 17. Read/projection surfaces

Implement rebuildable Sales reads:
- Quote conversion progress;
- Order effective version;
- reserved from Inventory;
- shipped from posted net Dispatch;
- invoiced remains zero/not-posted until Finance-integrated Invoice authority exists;
- remaining-to-ship;
- Invoice draft source eligibility;
- approval status/evidence;
- Dispatch state/history;
- source-target navigation.

No mutable copied reserved/shipped/invoiced total is authoritative.

### 18. API / Mars.Web

Protected API surface covers included capabilities.

Expected route families:
- /api/v1/sales/quotes
- /api/v1/sales/orders
- /api/v1/sales/orders/{id}/reservations
- /api/v1/sales/dispatches
- /api/v1/sales/invoices
- optional /api/v1/sales/proformas

No Sales Invoice POST/REVERSE endpoint in this tranche.

Mars.Web:
- /sales workspace;
- Quote list/detail/revision/conversion;
- Order list/detail/effective version/amendment;
- explicit Reservation action;
- Dispatch list/detail/POST/reverse/handoff;
- Invoice draft/source/calculation screen;
- source/processed/remaining/approval visibility;
- no Invoice paid/open amount;
- no Collection allocation;
- no stock overwrite;
- no Pick/Pack workflow ownership.

Use existing Mars.UI/Mars.Grid/Mars.Lookup/API client patterns.

### 19. Persistence boundary

Expected schema:
- sales

Expected normalized Sales structures:
- quotes
- quote_revisions
- quote_lines
- quote_conversion_links
- sales_orders
- sales_order_versions
- sales_order_lines
- sales_order_amendments
- sales_order_amendment_lines or equivalent normalized delta structure
- dispatches
- dispatch_lines
- dispatch_inventory_effect_links
- sales_invoices
- sales_invoice_lines
- sales_invoice_source_links
- optional proformas/proforma_lines

Shared/source-backed support:
- Foundation Approval Decision physical implementation
- Inventory/Warehouse Access Grant physical implementation

Rules:
- BIGINT internal PK;
- UUID public ids;
- trusted Company scope;
- optional trusted Branch snapshot only when execution context supplies it; client does not author branch;
- caller-supplied document number until Settings/Numbering exists;
- deterministic company/document-kind/number uniqueness for the first tranche;
- same-company source-target FK/compatibility;
- positive quantity;
- append/version history for accepted/processed records;
- no nullable mega-table;
- historical Party/Product/UOM/commercial snapshots are intentional denormalization;
- PostgreSQL authoritative;
- no JSON/EAV escape for core relations.

Exact NUMERIC money/quantity precision/scale is a database-architect decision before migration generation.
Quantity should remain compatible with existing Product/Inventory decimal precision.
Float/double is forbidden.

### 20. Durable concurrency/idempotency

PostgreSQL must durably protect:
- Quote conversion cumulative cap;
- stale Quote revision mutation;
- stale Order version/amendment activation;
- Order amendment processed floor;
- Reservation release/amendment race;
- Dispatch cumulative source cap;
- Dispatch POST duplicate;
- physical stock race via Inventory;
- Dispatch reversal duplicate;
- Invoice draft source identity/company compatibility.

Externally retryable state-changing commands use Foundation durable idempotency.

Valkey is not correctness authority.

### 21. Migration/Actions

Additive Sales implementation may affect:
- sales schema;
- foundation Approval Decision;
- inventory Warehouse Access Grant.

Cross-schema change is explicitly source-backed by Sales dependency and reviewed by Database/Architecture/Security.

Migration safety must include Migrations/Sales when committed.

Use the established Product/Inventory migration generation pattern:
- generation workflow may create/verify the migration;
- after migration is committed, generator becomes manual/read-only;
- no repeated duplicate migration generation.

## Explicit exclusions

SALES-IMP-001 does NOT implement:
- Finance Account Ledger;
- Inventory Valuation;
- Dispatch Cost Bridge;
- COGS ledger;
- Sales Invoice POST;
- Sales Invoice reversal;
- Collection;
- Invoice allocation/open-item settlement;
- Invoice paid/open-balance authority;
- cash/bank effects;
- TCMB/provider FX acquisition;
- manual FX override posting;
- e-Invoice/e-Archive/provider transmission;
- Sales Return/RMA workflow;
- Warehouse Pick/Pack/Stage/Load work;
- carrier/shipping provider integration;
- Sales Commercial Policy/price-list Settings administration;
- generic pricing engine;
- automatic document numbering allocator;
- production deployment;
- Full Test Day.

## Normal development acceptance evidence

Required for SALES-IMP-001 completion:
- targeted Sales domain/application/persistence tests;
- Quote revision/conversion cumulative-cap tests;
- approval/SoD tests;
- Party CUSTOMER/Product SELLABLE/company isolation tests;
- Order confirm no STOCK/ACCOUNT tests;
- controlled amendment stale/processed-floor/reservation-release tests;
- explicit Reservation create/increase/release integration tests;
- Dispatch source-cap tests;
- Warehouse-scope authorization tests;
- Dispatch POST atomic Sales + Inventory + Reservation tests;
- no-negative/no-double-stock behavior via Inventory;
- Dispatch reversal tests;
- Invoice draft calculation/source tests;
- proof that Invoice POST/Reverse are not exposed;
- TRY calculation/rounding tests;
- frontend verify;
- Release build;
- additive generated migration;
- migration safety including Sales;
- EF pending-model clean;
- API/OpenAPI smoke;
- TEST deploy/smoke;
- /sales web route 200 when implemented;
- /inventory, /products, /parties regression 200;
- /health/live and /health/ready 200;
- protected Sales endpoints unauthenticated 401;
- OpenAPI includes included Sales public surface.

Do not claim:
- authenticated TEST Sales mutation without direct evidence;
- Invoice financial posting;
- Finance ledger effects;
- production deployment.

Heavy PostgreSQL concurrency, broad authenticated permission/IDOR matrix, browser E2E, high-volume Sales search/reporting, performance/load, backup/restore and full cross-module accounting/stock invariants remain Full Test Day.

## SOURCE / INFERENCE / UNKNOWN / BLOCKED

SOURCE:
- PLAN-002 freezes Quote/Order/Reservation/Dispatch/Invoice effects and source-target behavior.
- PLAN-010 assigns Sales commercial documents to Sales, Reservation/stock to Inventory, receivable/value/COGS to Finance.
- PLAN-006 supersedes operational pick/pack ownership and keeps Dispatch POST Sales-owned.
- Inventory authority is implemented and joins an ambient MarsDbContext transaction.
- Finance Account/Valuation authority is not implemented.
- Settings/Sales Commercial Policy is not implemented.
- Warehouse operational work is not implemented.
- owner requires broad coherent tranches.

INFERENCE / ordinary technical decisions:
- the first broad Sales tranche stops at Finance-dependent Invoice POST rather than duplicating Finance;
- Invoice draft/source/calculation authority is still included so Sales commercial ownership is established now;
- Warehouse resource scope is represented by a Warehouse-owned access grant separate from Foundation action permission;
- caller-supplied document numbers are used until Settings/Numbering exists;
- no-policy approval path is fail-closed;
- Dispatch reversal does not automatically recreate Reservation because Reservation initiation is explicitly manual.

UNKNOWN / deferred but non-blocking:
- exact future Sales Commercial Policy pricing/tolerance model;
- non-TRY server-side currency minor-unit configuration;
- TCMB/provider adapter implementation;
- exact Finance posting interface for receivable/COGS;
- e-document provider;
- automatic numbering format/allocator;
- later Warehouse work orchestration.

BLOCKED:
- Sales Invoice POST/REVERSE and financial completion are blocked until Finance Account/Valuation/Dispatch Cost Bridge authority is implemented.
- non-TRY final authoritative commercial rounding/FX finalization is blocked until server-side currency/FX authority exists.
- these blockers do not block SALES-IMP-001 because those capabilities are explicitly outside this tranche.

## Decision

Assigned:
SALES-IMP-001 — Sales Commercial & Fulfillment Authority Tranche

Status:
READY FOR IMPLEMENTATION

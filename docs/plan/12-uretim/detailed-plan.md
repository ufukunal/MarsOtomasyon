# Production — Detailed Plan

Status: DETAILED PLANNING FROZEN

## Product surface from V38
BOM/Reçeteler, Rotalar, İş Merkezleri, ECO, Üretim Emirleri, Shop Floor, Üretim Çıktıları, Duruşlar, WIP/Üretim Maliyeti, Üretim Raporu, OEE. V38 later patches also expose simplified Malzeme Çıkışı, Mamul Girişi and Fason Takibi.

## Ownership
Production owns BOM/routing/work execution and production document state. Product owns sellable/stockable item master. Inventory owns physical quantity. Quality owns inspection decisions. Finance owns WIP/value/cost accounting. Maintenance owns maintenance work.

## Core records
Bom, BomRevision, BomComponent, Routing, RoutingRevision, RoutingOperation, WorkCenter, EquipmentRef, EngineeringChangeOrder, ProductionOrder, ProductionOrderMaterial, ProductionOperation, MaterialIssueRequest/Link, ProductionOutput, ScrapEvidence, DowntimeEvidence, GenealogyLink.

## Workflow/state
BOM/Route: DRAFT -> APPROVAL_REQUIRED -> ACTIVE -> SUPERSEDED/RETIRED.
ECO: DRAFT -> REVIEW -> APPROVED -> APPLIED -> CLOSED.
Production Order: DRAFT -> RELEASED -> IN_PROGRESS -> PARTIALLY_COMPLETED -> COMPLETED | CANCELLED_REMAINDER | CLOSED.
Operation: READY -> IN_PROGRESS -> COMPLETED | SKIPPED_BY_APPROVAL.
Output is posted evidence; correction uses reversal/compensation, not edit.

## Effects
DOC: yes.
RES: Production may initiate Inventory reservation for components.
STOCK: material issue = Inventory OUT; material return = Inventory IN; finished/by-product output = Inventory IN. No duplicated stock table.
ACCOUNT/CASH: none.
COST: Production records operational quantities/time; Finance/Costing owns WIP, absorption, valuation and variance.

## Rules
Exact BOM revision and routing revision snapshot on release. Released/processed material identity cannot silently change. Partial issue/output allowed with cumulative source caps. Lot/Serial genealogy links consumed components to output lots/serials. Scrap is explicit evidence and physical effect through Inventory.

## Data
Normalized BOM/routing revisions, operations, materials, outputs, genealogy and downtime. Decimal quantity/time; no float accounting truth. Same-company Product/UOM/Warehouse references. Append history for released/processed documents.

## Permissions
production.bom.*, production.routing.*, production.eco.*, production.order.*, production.material.issue/return, production.operation.*, production.output.*, production.scrap.*, production.downtime.read/manage.

## API/UI
/api/v1/production/boms, /routings, /work-centers, /ecos, /orders, /material-issues, /outputs, /downtime.
UI follows V38 list/detail tabs and source genealogy.

## Concurrency
Release revision lock; material/output cumulative caps; duplicate issue/output prevention; serial uniqueness delegated to Inventory; operation completion stale writes blocked.

## Acceptance
BOM revision, release snapshot, partial material issue/output, no double-stock, genealogy, reversal, company isolation, permission and Finance-boundary tests.

## UNKNOWN
Exact costing method, backflush policy, alternate-component substitution policy, overlap/setup rules and detailed OEE formula are implementation-time policy gates unless later source freezes them.

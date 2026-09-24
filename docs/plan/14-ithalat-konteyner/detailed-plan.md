# Import / Container — Detailed Plan

Status: DETAILED PLANNING FROZEN

## V38 product surface
İthalat Dosyaları, Yeni İthalat Dosyası, Konteynerler, Koli/Komponent Eşleştirme, Landed Cost, Konteyner Simülatörü, Teknik Ürün Dosyası.

## Ownership
Import owns shipment/container/document coordination and allocation evidence. Purchasing owns PO/supplier commercial authority. Inventory owns receipt quantity. Finance owns landed-cost valuation/accounting and FX/tax accounting. Files owns binary documents.

## Records
ImportCase, Shipment, Container, ContainerSeal, Package, PackageProductMap, Milestone, ImportDocumentLink, ChargeEvidence, LandedCostAllocationProposal/Evidence, TechnicalProductFileLink, SimulationScenario.

## Workflow
Case DRAFT -> BOOKED -> IN_TRANSIT -> ARRIVED -> CUSTOMS/PROCESSING -> PARTIALLY_RECEIVED -> RECEIVED -> COST_RECONCILED -> CLOSED.
Container maintains its own transport milestones; simulations are non-authoritative.

## Effects
DOC: yes. RES: none. STOCK: only linked Purchasing Goods Receipt/Inventory authority creates stock. ACCOUNT/CASH: none directly. COST: import captures evidence/allocation proposal; Finance posts landed cost.

## Rules
Container/package/product quantities reconcile to source PO/shipment; partial container receipt allowed; short/damaged evidence explicit; shipment documents immutable after finalization except version/replacement. Landed-cost allocation never directly mutates Inventory Ledger.

## Data
Shipment/container/package normalized relations, weights/volume/qty/value decimal, source currency/rate evidence snapshots, document links, port/carrier references, milestone timestamps.

## Permissions/API/UI
import.case.*, import.container.*, import.package_map.*, import.document.*, import.landed_cost.prepare, import.simulate.
Routes /api/v1/import/cases, /containers, /packages, /landed-cost-proposals, /simulations.

## Acceptance
PO linkage, package reconciliation, partial receipt linkage, short/damage evidence, cost-boundary proof, document authorization, company isolation.

## UNKNOWN
Customs tariff engine, exact duty/tax formulas, Incoterm policy and external customs/provider adapters are not specified by V38 and remain implementation-time gates.

# Fixed Assets — Detailed Plan

Status: DETAILED PLANNING FROZEN

## V38 product surface
List: Varlık, Açıklama, Equipment Link, Alış Tarihi, Maliyet, Lokasyon, Depreciation Boundary, Durum.
Detail tabs: Genel, Equipment Link, Maliyet, Belgeler, Timeline.

## Ownership
Fixed Assets owns asset registry, operational lifecycle, location/custodian and source evidence. Finance owns capitalization/depreciation/impairment/revaluation accounting. Maintenance owns maintenance work.

## Records
FixedAsset, AssetClassRef, AcquisitionSourceLink, EquipmentLink, Location/CustodianHistory, AssetComponent, DisposalEvidence, FinancePostingLinks, Document/FileLinks.

## Workflow
DRAFT -> ACTIVE -> SUSPENDED/IDLE -> DISPOSAL_PENDING -> DISPOSED; transfers append custody/location history.

## Effects
DOC yes. STOCK generally none unless a separately stock-managed item workflow is explicitly linked. ACCOUNT/CASH/COST no direct posting; Finance authority required.

## Rules
Acquisition value/date are source snapshots, not editable accounting truth after capitalization. Equipment link may be one-to-one or versioned according to implementation. Disposal/sale requires linked Finance/Sales effects rather than local balance mutation.

## Permissions/API/UI
asset.read/create/edit/activate/transfer/dispose; asset.finance_links.read.
Routes /api/v1/fixed-assets, /transfers, /disposals.

## Acceptance
Source lineage, transfer history, disposal boundary, no local depreciation posting, permission/company isolation.

## UNKNOWN
Book/tax depreciation methods, componentization thresholds, impairment/revaluation policy and capitalization rules belong to Finance policy and are not fixed by V38.

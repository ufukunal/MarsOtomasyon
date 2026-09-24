# Maintenance — Detailed Plan

Status: DETAILED PLANNING FROZEN

## V38 product surface
Bakım Yönetimi list columns: WO, Equipment, Tip, Reported, Planned, Start, End, Downtime, Spare Cost, Labor, Durum.
Detail tabs: Talep, Plan, İşçilik, Yedek Parça, Downtime, Cost, Evidence, Timeline.
Actions: Triaged, Planla, Başlat, Complete, Close.

## Ownership
Maintenance owns maintenance requests/orders, preventive schedules, labor/downtime evidence and equipment maintenance history. Inventory owns spare-part stock; Purchasing owns external procurement; Fixed Assets owns asset identity where linked; Finance owns monetary cost accounting.

## Records
EquipmentReference, MaintenanceRequest, MaintenanceOrder, Plan/Schedule, Checklist, LaborEntry, SparePartRequirement, SparePartIssueLink, Downtime, FailureCode, RootCauseRef, ExternalServiceLink, EvidenceAttachment.

## Workflow/state
Request: REPORTED -> TRIAGED -> PLANNED -> CONVERTED/CLOSED.
Order: DRAFT/PLANNED -> READY -> IN_PROGRESS -> COMPLETED -> CLOSED; CANCELLED before irreversible effects.
Preventive schedule: ACTIVE -> DUE -> GENERATED -> next recurrence.

## Effects
DOC yes. RES may request Inventory reservation for spare parts. STOCK only through Inventory issue/return. ACCOUNT/CASH none directly. COST operational evidence only; Finance owns posting.

## Rules
Spare issue must link exact maintenance order and Product/UOM/Warehouse. Unused parts return through Inventory. Downtime intervals cannot silently overlap for same exclusive equipment when configured. Completion requires required checklist/evidence gates.

## Permissions/API/UI
maintenance.request.*, maintenance.order.*, maintenance.plan.*, maintenance.labor.*, maintenance.parts.*, maintenance.complete/close.
Routes /api/v1/maintenance/requests, /orders, /plans, /equipment-history.

## Acceptance
Lifecycle, preventive generation idempotency, part issue/return, downtime, stale completion, permissions, company/equipment scope and Finance boundary.

## UNKNOWN
Exact meter source, preventive recurrence DSL, cost capitalization policy and maintenance SLA thresholds are implementation gates.

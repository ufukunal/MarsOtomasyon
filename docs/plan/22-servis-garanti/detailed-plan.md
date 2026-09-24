# Service / Warranty — Detailed Plan

Status: DETAILED PLANNING FROZEN

## V38 product surface
Servis/Garanti list: Servis No, Cari, Ürün/Seri, Garanti, Talep, RMA, Teknisyen, Termin, Durum.
Detail tabs: Talep, Ürün/Seri, Garanti, Teşhis, İşçilik, Parça, RMA, Maliyet, Timeline.
Actions: Teknisyen Ata, Parça Çık, Tamamla.

## Ownership
Service owns service case, diagnosis, technician/work evidence and warranty eligibility decision. Returns owns RMA/physical return lifecycle. Inventory owns parts. Sales/Finance own billable document/accounting/refund.

## Records
ServiceCase, ProductSerialSnapshot, WarrantyEvidence, Diagnosis, TechnicianAssignment, LaborEntry, PartRequirement/IssueLink, RmaLink, ExternalServiceLink, CompletionEvidence.

## Workflow
OPEN -> TRIAGED -> ASSIGNED -> DIAGNOSING -> WAITING_PARTS | WAITING_CUSTOMER | IN_REPAIR -> COMPLETED -> CLOSED; CANCELLED where no irreversible effect.
Warranty decision PENDING -> ELIGIBLE | NOT_ELIGIBLE | EXCEPTION_APPROVED.

## Effects
DOC yes; RES optional for parts; STOCK only Inventory part issue/return; ACCOUNT/CASH none; COST evidence only.

## Rules
Serial/Product/Party lineage preserved. Warranty approval does not itself create refund/replacement stock. Replacement/return routes through Returns/Sales/Inventory as appropriate. Repeated service remains linked history.

## Permissions/API/UI
service.case.*, service.assign, service.diagnose, service.parts.issue, service.warranty.decide/override, service.complete.
Routes /api/v1/service/cases, /warranty-decisions, /history.

## Acceptance
Serial lineage, warranty dates/evidence, parts issue, RMA link, no duplicate physical/refund effect, permission and company isolation.

## UNKNOWN
Warranty-policy formula, SLA, chargeable labor pricing and manufacturer claim/provider workflow are not frozen by V38.

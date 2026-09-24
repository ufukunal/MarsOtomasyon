# Contracts / Periodic Operations — Detailed Plan

Status: DETAILED PLANNING FROZEN

## V38 product surface
Sözleşme/Periyodik list: Sözleşme, Cari, Başlangıç, Bitiş, Periyot, Tutar, Son Üretim, Sonraki, Durum.
Detail tabs: Genel, Fiyatlama, Schedule, Generated Docs, Renewal, Files, Timeline.
Actions: Generate, Renew, Terminate.

## Ownership
Contracts owns contract/version/schedule obligations and generation evidence. Generated commercial/financial transactions belong to Sales/Purchasing/Finance.

## Records
Contract, ContractRevision, ContractParty, ContractLine/Scope, PricingSnapshot/RuleRef, ScheduleRule, GenerationOccurrence, GeneratedDocumentLink, Renewal, Termination, FileLink.

## Workflow
DRAFT -> APPROVAL_REQUIRED -> ACTIVE -> SUSPENDED -> EXPIRED/TERMINATED; renewal creates linked revision/contract period.
Occurrence: PLANNED -> DUE -> GENERATED | SKIPPED_APPROVED | FAILED_RETRYABLE.

## Effects
Contract itself has DOC effect only. Scheduled generation calls owning module; no direct STOCK/ACCOUNT/CASH/COST bypass.

## Rules
Generation occurrence has deterministic idempotency identity. Contract revision immutable after activation. Future occurrences use effective revision; prior generated docs retain snapshot. Termination cancels only ungenerated future obligations unless owning-module reversal separately applies.

## Permissions/API/UI
contract.read/create/edit/approve/activate/suspend/renew/terminate/generate.
Routes /api/v1/contracts, /schedules, /occurrences.

## Acceptance
Version/effective-date, duplicate scheduler prevention, generated-document lineage, terminate/renew, permission, timezone and stale revision tests.

## UNKNOWN
Recurrence grammar, indexation formula sources and which document types are initially supported are implementation-time configuration decisions.

# Quality — Detailed Plan

Status: DETAILED PLANNING FROZEN
Source basis: V38 UI reference + frozen Mars module/ledger boundaries.

## Product surface from V38
Screens/routes: Kontrol Planları (qcp_list/qcp_new), QC Bekleyenler, Kalite Kontrolü, QC Disposition, Uygunsuzluklar, DÖF/CAPA, 8D/Kök Neden, Kalibrasyon, Tedarikçi Kalitesi, SPC, Kalite Analitiği.

## Ownership
Quality owns inspection plan/work/evidence, nonconformance, CAPA/root-cause workflow, calibration evidence and quality decision.
Inventory owns physical quantity/disposition ledger. Product owns item master. Purchasing/Production/Returns own their source documents. Finance owns valuation/cost truth.

## Core records
QualityControlPlan, PlanRevision, Characteristic, SamplingRule, Inspection, InspectionSample, Measurement, DispositionDecision, Nonconformance, Capa, RootCauseCase, CalibrationRecord, SupplierQualityObservation.

## Workflow/state
Plan: DRAFT -> ACTIVE -> RETIRED; revisions append history.
Inspection: PENDING -> IN_PROGRESS -> PASSED | FAILED | CONDITIONAL | CANCELLED.
Nonconformance: OPEN -> INVESTIGATING -> ACTION_REQUIRED -> VERIFIED -> CLOSED.
CAPA: DRAFT -> APPROVAL_REQUIRED -> ACTIVE -> EFFECTIVENESS_REVIEW -> CLOSED.
Calibration: DUE -> IN_PROGRESS -> PASSED | FAILED -> CLOSED.

## Effects
DOC: yes. RES: none by Quality itself. STOCK: no direct quantity mutation; Quality may request Inventory disposition transition. ACCOUNT/CASH: none. COST: evidence only; Finance consumes approved evidence if needed.

## Source/target
Inspection must reference exact source context when triggered by Goods Receipt, Production output, Return/RMA, Lot/Serial or ad-hoc authorized inspection. Result must never rewrite source history.

## Data/constraints
Company scope, UUID public ids, BIGINT internal ids, append-only revisions/results after decision, decimal measurements, unit snapshot, lower/upper limits when present, unique sample/measurement identity, immutable actor/time/equipment evidence.
No JSON/EAV escape for core characteristics/results.

## Permissions
quality.plan.read/manage/activate; quality.inspection.read/start/record/complete; quality.disposition.decide; quality.nonconformity.manage; quality.capa.manage/approve/verify; quality.calibration.manage; quality.supplier.read; quality.spc.read.

## API/UI
/api/v1/quality/plans, /inspections, /dispositions, /nonconformities, /capas, /calibrations, /supplier-quality.
Mars.Web preserves V38 list/detail/timeline structure and source navigation.

## Concurrency/idempotency
No duplicate inspection completion, no second final disposition, stale plan revision blocked, repeat measurement operation idempotent, source quantity/lot/serial revalidated by owning authority.

## Acceptance
Targeted plan-revision, inspection state, measurement limits, SoD, source-lineage, disposition handoff, duplicate completion and company isolation tests; UI route smoke; migration/model drift checks.

## UNKNOWN / implementation gate
Exact sampling algorithms, AQL tables, characteristic catalog, SPC formula set and automatic disposition policy are not frozen by V38. They must be explicitly configured/source-backed before implementation depends on them.

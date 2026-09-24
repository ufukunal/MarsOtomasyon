# Segregation of Duties / Control Center — Detailed Plan

Status: DETAILED PLANNING FROZEN

## V38 product surface
Segregation of Duties custom screen plus Role detail SoD tab, Approval detail Four-Eyes tab, audit/critical action surfaces.

## Ownership
SoD owns conflict definitions, control evaluation, review evidence and remediation workflow. Foundation/module authorization remains runtime enforcement authority.

## Records
ConflictRule, ConflictPermissionSet, ControlEvaluation, Violation, ExceptionApproval, AccessReview, RemediationEvidence, CriticalActionObservation.

## Control families
creator vs approver; request vs approve; post vs reverse where policy requires; payment/treasury conflicts; stock count vs discrepancy approval; scrap request vs approval; role-netting/FX/manual override; privileged Settings/backup/restore; cross-company/warehouse scope.

## Workflow
Violation DETECTED -> REVIEWING -> REMEDIATION_REQUIRED | EXCEPTION_REQUESTED -> RESOLVED/EXCEPTION_ACTIVE -> CLOSED.
Access review DRAFT -> IN_REVIEW -> CERTIFIED/REVOKE_REQUIRED -> CLOSED.

## Effects
No business STOCK/ACCOUNT/CASH effect. Exception evidence never bypasses module permission checks unless exact owning policy consumes it.

## Rules
Rules versioned; evaluation stores exact grant/permission snapshot and timestamp. Revoked grants disappear from current exposure but remain history. Exception has scope, expiry, approver and reason.

## Permissions/API/UI
sod.read, sod.rule.manage, sod.review, sod.exception.request/approve, sod.remediate.
Routes /api/v1/sod/rules, /evaluations, /violations, /reviews.

## Acceptance
Conflict fixtures, four-eyes, expired exception, revoked grant, cross-company leakage, immutable evidence, rule-version replay.

## UNKNOWN
Initial conflict matrix must be derived from implemented permission catalog at activation; V38 does not provide a complete matrix.

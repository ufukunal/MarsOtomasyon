# Device Layer — Detailed Plan

Status: DETAILED PLANNING FROZEN

## Product scope
Scanner/barcode/camera/printer routing, ESC/POS, ZPL/TSPL/RAW printing, device registration and offline operation identity are master-plan requirements. V38 also contains Scan Console and Print Center concepts; historical patches may deprecate a particular route, not the capability.

## Ownership
Device layer owns device registration/trust, platform adapters, scan/print transport and offline queue mechanics. Business validation remains server-side in owning module.

## Records
DeviceRegistration, DeviceAssignment, DeviceCapability, PrinterProfile, PrintRoute, PrintJob, ScanEventEnvelope, OfflineOperation, SyncAttempt, DeviceHeartbeat.

## Device lifecycle
PENDING -> TRUSTED -> SUSPENDED/REVOKED; key/credential rotation audited.

## Print lifecycle
QUEUED -> ROUTED -> SENT -> ACKNOWLEDGED where supported | FAILED_RETRYABLE | FAILED_FINAL. A print success is not a business posting success.

## Offline lifecycle
LOCAL_PENDING -> SYNCING -> ACCEPTED | CONFLICT | REJECTED | RETRYABLE_FAILURE. Durable client operation id is mandatory.

## Rules
Barcode input is data/evidence, never permission bypass. Server revalidates company, warehouse, source version, stock and business rules. Duplicate scan/operation idempotent. Device cannot author trusted company scope.

## Security
Device trust/revoke, least-privilege token, no embedded production secrets, payload minimization, local sensitive-data retention limits.

## Permissions/API/UI
device.register/manage/assign/read, print.submit/read/retry, scan.use.
Routes /api/v1/devices, /print-jobs, /offline-sync.

## Acceptance
Duplicate offline retry, stale conflict, revoked device, printer failure, route selection, barcode mismatch, company/warehouse enforcement.

## UNKNOWN
Exact Desktop/Mobile shell and hardware SDKs are ADR/implementation gates.

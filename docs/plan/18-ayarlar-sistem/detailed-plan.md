# Settings / System — Detailed Plan

Status: DETAILED PLANNING FROZEN

## V38 product surface
Firma Ayarları, Şubeler, Kullanıcılar, Roller/İzinler, Onaylar, Numaralandırma, Vergi/Döviz, Dönemler, Audit, Outbox, Sistem Sağlığı, Queue/Jobs, Yedekleme, Restore, Recovery Mode, Bulk Import/Export, Veri Migrasyonu, Retention/Archive.

## Ownership
Settings owns explicit configuration authorities; Foundation owns identity/auth/permission primitives/audit/outbox mechanics; Finance owns financial posting policy semantics; modules consume settings without retroactively rewriting posted history.

## Configuration families
Company/base currency/timezone/status; Branch scope; role/group administration; approval policies; numbering sequences; currency/minor-unit metadata; tax configuration references; posting-period gates; provider configuration references; feature/system parameters; retention policies.

## Numbering
Company/document-kind + optional branch/period scope. Atomic sequence issue, unique issued number, no client-side allocation, no reuse after committed issue unless explicit void policy.

## Approval
Policy identifies target/action/threshold context; decision binds exact version/hash; requester/creator != approver when four-eyes required; mutation invalidates stale approval.

## Company/Branch
Company suspension blocks writes/scheduler/outbound delivery by default, matching V38 note. Branch scope is explicit and server-trusted.

## System operations
Audit read; outbox retry/manual review; jobs visibility; health; backup/restore/recovery controls; bulk import/export; migration; retention/archive.

## Permissions
settings.company.*, branch.*, identity.admin.*, role.*, approval.policy.*, numbering.*, currency.*, tax.*, posting_period.*, audit.read, outbox.admin, jobs.admin, backup/restore/recovery privileged actions.

## Data
Versioned configuration rows with effective timestamps where history matters; secrets stored by secret reference, never plaintext config/audit; critical settings carry audit/approval evidence.

## Acceptance
Atomic numbering concurrency, approval hash binding, company suspension gate, posting-period gate, secret redaction, role scope, bulk import idempotency and restore/recovery authorization.

## UNKNOWN
Exact tax engine, currency-rate provider, secret store, backup backend and scheduler technology remain separate implementation gates.

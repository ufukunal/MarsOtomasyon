# Release Hardening — Detailed Plan

Status: DETAILED PLANNING FROZEN / EXECUTION NOT STARTED

## Production topology
Final hosts/network/DNS/TLS/reverse proxy or tunnel; app/API/Worker; PostgreSQL; Valkey; object storage; provider connectivity; environment separation.

## Identity/secrets
Production secret store decision; service identities; DB owner/migration/runtime roles; credential rotation; OIDC keys/certs; provider secrets; no plaintext repository secrets.

## Deployment
Immutable build/artifact identity; configuration validation; migration preflight; controlled migration identity; health/readiness; smoke; rollback/forward-fix decision tree; zero false success.

## Database
Migration rehearsal from production-like backup, lock/downtime assessment, backup-before-change, restore evidence, schema/model drift gate.

## Backup/DR
Schedule, encryption, retention, offsite copy, restore test, RPO, RTO, incident ownership.

## Observability
Structured logs, correlation, metrics, traces where selected, dashboards, alert thresholds, queue/outbox/provider failure visibility, disk/DB/backup alerts.

## Capacity
CPU/RAM/storage/DB connections, worker concurrency, queue throughput, web/API latency, report workload, provider limits.

## Security hardening
TLS, headers, auth/session, least privilege, secret redaction, dependency/license review, file/webhook protections, admin surface restrictions, audit retention.

## Runbooks
Deploy, migrate, rollback/forward-fix, backup, restore, credential rotation, provider outage, queue backlog, database incident, security incident, support escalation.

## Release checklist
Full Test Day evidence; unresolved defects; migration evidence; backup/restore; monitoring; production smoke; owner acceptance; go/no-go record.

## Production prohibition
No production deployment occurs merely because this plan is complete. Explicit owner authorization is required.

## UNKNOWN
Exact production secret store, reverse proxy/tunnel, storage backend and final Desktop/Mobile distribution technology remain implementation/release ADR gates.

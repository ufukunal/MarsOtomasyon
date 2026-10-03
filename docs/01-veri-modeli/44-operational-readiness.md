# Faz 11 — Operasyonel hazırlık veri modeli

**Veritabanı: MASTER**

Business transaction tablolarından ayrıdır.

## deployment_runs

- id
- release_id
- commit_sha
- initiated_by
- initiated_by_name
- status: preparing|migrating|verifying|active|failed|rolled_back
- started_at
- finished_at
- previous_release_id nullable
- metadata jsonb
- error_summary nullable

## backup_runs

- id
- recovery_set_id
- trigger_type: scheduled|deploy|period_carry|manual
- status: running|done|failed|verified
- started_at
- finished_at
- storage_disk
- manifest_path
- master_backup_path
- files_backup_path nullable
- period_manifest jsonb
- checksum_manifest jsonb
- verified_at nullable
- error_summary nullable

## restore_runs

- id
- recovery_set_id
- source_backup_run_id
- target_type: temporary|archive|production_recovery
- status
- started_at
- finished_at
- verification_summary jsonb
- error_summary nullable

## operational_heartbeats

- service_key
- last_seen_at
- metadata jsonb nullable

Örnek:
- scheduler
- queue.default
- queue.channel-sync
- queue.reports

## health_check_runs

- id
- checked_at
- overall_status: healthy|degraded|failed
- checks jsonb
- correlation_id nullable

## failed job / framework tabloları

Framework queue failed-job kaydı kullanılır; duplicate operasyonel gerçek kaynak oluşturulmaz.

## Kurallar

- Secret/credential plaintext metadata'ya yazılmaz.
- Business data burada kopyalanmaz.
- Log/health metadata retention operasyon politikasına göre temizlenebilir.

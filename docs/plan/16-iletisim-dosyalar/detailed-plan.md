# Communications / Files — Detailed Plan

Status: DETAILED PLANNING FROZEN

## V38 product surface
Dosyalar, Dosya Güvenliği, Mesaj Şablonları, Teslimatlar, İletişim Providerları, Webhooklar; advanced communications is also listed later.

## Ownership
Module owns file metadata/access policy and communication delivery orchestration. Object storage/provider adapters are infrastructure. Business modules own the reason/content source.

## Records
FileObject, FileVersion, FileLink, SecurityScan, Template, TemplateRevision, Delivery, DeliveryAttempt, ProviderConfigReference, WebhookEndpoint/SecretRef, WebhookDeliveryAttempt, Consent/Preference reference where required.

## File workflow
PENDING_UPLOAD -> AVAILABLE -> QUARANTINED | BLOCKED -> ARCHIVED/DELETED_BY_POLICY.
Raw file bytes never become database business authority. Access is company/permission scoped.

## Delivery workflow
QUEUED -> SENDING -> SENT -> DELIVERED where provider supports it; FAILED_RETRYABLE | FAILED_FINAL | MANUAL_REVIEW. DB commit means queued, not delivered.

## Channels
Email, SMS, WhatsApp, push, in-app notification as provider capability allows. Templates are versioned and rendered from immutable payload snapshots for sent evidence.

## Security
MIME/type/size checks, malware scan, checksum, uploader identity, purpose, retention, authorization, signed/controlled download. Secrets/tokens never stored in audit raw diff.

## Permissions/API/UI
files.read/upload/delete/archive/security.read; communication.template.*, delivery.read/retry, provider.manage, webhook.manage.
Routes /api/v1/files, /templates, /deliveries, /providers, /webhooks.

## Concurrency/idempotency
Checksum/dedupe where intended, idempotent delivery operation, webhook replay protection and attempt uniqueness.

## Acceptance
Unauthorized download, scan block, immutable sent template snapshot, retry/dedup, provider failure classification, secret redaction and company isolation.

## UNKNOWN
Exact object storage, malware engine and communication providers remain ADR/implementation gates.

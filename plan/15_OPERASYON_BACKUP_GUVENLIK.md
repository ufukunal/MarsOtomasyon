# 15 — Operasyon, Backup, Güvenlik ve Gözlemlenebilirlik V4

## 1. Production prensibi
Business truth PostgreSQL'dedir. Valkey/queue/cache/search index kaybı permanent business state kaybı değildir.

İlk deployment hedefi tek sunucu/az kullanıcıdır; buna rağmen restore, idempotency ve integrity kontrolleri zorunludur.

## 2. Sistem Sağlığı
Admin operasyon ekranında en az:
- uygulama/DB erişimi
- Valkey
- queue worker
- scheduler heartbeat
- disk/storage
- failed jobs
- queue age
- outbox backlog/oldest pending
- integration failed/ambiguous count
- projection/balance/stock mismatch alerts
- PostgreSQL search/index health
- son backup/restore drill
izlenebilir.

Normal kullanıcıya teknik detay değil anlaşılır durum gösterilir; admin correlation/log detayına gidebilir.

## 3. Correlation / observability
Correlation ID request → transaction → Outbox → job → provider zincirinde taşınır. Finans/stok business audit application log'dan ayrı authority değildir ama forensic kaynağıdır.

Secret/PII redacted loglanır.

## 4. Company execution gate
Worker/scheduler/outbound delivery her execution'da company state'i kontrol eder. Suspended company outbound/mutation çalıştırmaz; backlog durable kalır. Reactivation sonrası idempotency/staleness ile kontrollü resume edilir. Archived company normal provider processing'e dönmez.

## 5. Backup manifest
Her BackupRun manifest'i en az:
- PostgreSQL backup referansı
- files/object manifest
- checksums
- gerekli runtime/config metadata referansları
- application release/commit
- schema/migration version
- created_at/cutoff
- encryption/key-version metadata

taşır.

Secret plaintext manifest'e yazılmaz.

## 6. Offsite / retention
Aynı disk üzerindeki tek kopya backup kabul edilmez. Günlük/haftalık/aylık retention ve offsite/S3-compatible veya eşdeğer hedef deployment'ta tanımlanır.

## 7. Backup encryption / key separation
Encrypted backup artifact ile onu açan tek recovery key aynı storage/security boundary içinde tutulmaz. Recovery key erişimi least-privilege olmalıdır.

## 8. DB + file consistency
Critical posted PDF/XML/files immutable/versioned object key ve checksum ile manifest'e bağlanır. Restore sonrası referenced artifact bulunmalı ve checksum eşleşmelidir.

## 9. Restore
Backup ancak restore prosedürü test edildiyse tamamdır.

Restore drill:
1. izole hedef hazırla
2. manifest/compatible application version doğrula
3. DB restore
4. files restore
5. decrypt/key access doğrula
6. controlled forward migration gerekiyorsa uygula
7. checksum/integrity/reconciliation
8. critical smoke
9. sonucu `RestoreRun` olarak kaydet

Arbitrary eski DB bugünkü code ile kör boot edilmez.

## 10. Disaster Recovery / Recovery Mode
Recovery Mode fail-closed olarak:
- browser write
- API mutation
- CLI business mutation
- queue/scheduler automation
- outbound provider delivery
bloklayabilir.

Recovery-safe inbound yalnız durable Inbox/quarantine'a alınabilir.

Mutation açılmadan önce en az:
- sequence integrity
- external IDs/mappings
- Inbox/Outbox cutoff
- ambiguous provider outcomes
- cari/stok/cash/bank reconciliation
- desired stock/price state
kontrol edilir.

## 11. Queue operasyonu
- retry/backoff
- failed jobs visibility
- idempotent handler
- poison-message loop prevention
- bounded lease/stale recovery
- permission/audit controlled replay

Queue retry business/source-effect uniqueness'i bypass edemez.

## 12. Scheduler
Aynı kritik job'ın yarışmasını lock ile önle. Son çalışma, süre ve hata görünürlüğü sağla.

## 13. Provider kill-switch
Inbound/outbound provider veya kanal secret silmeden disable edilebilir. Backlog durable kalır. Resume capability/idempotency/staleness kontrolünden geçer.

## 14. File security operations
Untrusted upload `pending/quarantined` iken normal preview/download/report/thumbnail source'u olamaz. Clean sonrası erişilir. Scan timeout/failure fail-open değildir.

## 15. Credential operations
Provider secret:
- encrypted storage
- masked read-back
- rotate/değiştir
- `last_test_at/status`
- disable without deleting secret
ile yönetilir.

## 16. Encryption rotation
Historical ciphertext gerekli süre okunabilir olmalıdır. Key rotation backup restore kabiliyetini bozmaz.

## 17. Production correction
Tinker/direct SQL business edit yok. Reversal/adjustment veya controlled idempotent audited correction kullanılır.

## 18. Güvenlik hardening
- production debug kapalı
- HTTPS
- secure cookie/session
- uygun security headers
- least-privilege DB user
- Valkey public internete açık değil
- doğru file permissions
- admin/credential/backup/restore audit
- dependency/license inventory
- 2FA policy

## 19. Retention
Log/audit/import payload/file retention ayrı tanımlanır. Gereksiz PII süresiz tutulmaz. Legal/business kayıtların yasal saklama gereksinimi ayrıca uygulanır.

## 20. Release gate
Deploy öncesi:
- CI green
- migration review
- backup readiness
- backward-compatible deploy sırası
- smoke test
- forward-fix/reversal planı

Destructive schema değişikliği tek kör release'te yapılmaz.

## 21. Deployment
Native/CyberPanel/Docker nihai seçim `19_ACIK_KARARLAR.md` içinde kapanır. Domain kodu web server/deploy aracına bağlı tasarlanmaz.

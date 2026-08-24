# 15 — Operasyon, Backup ve Güvenlik

## Sistem sağlığı
Operasyon ekranı en az:
- uygulama/DB erişimi
- Valkey
- queue worker
- scheduler
- disk
- storage
- son backup
- başarısız job
- entegrasyon hata sayısı
- kritik cron/worker durumu
izlenebilirliğini verir.

Normal kullanıcıya teknik ayrıntı yerine anlaşılır durum gösterilir; admin detayına correlation/log bağlantısı verilebilir.

## Backup
Tek backup run manifest'i:
- PostgreSQL dump/base strategy
- upload/private files
- gerekli runtime/config metadata
- version/schema bilgisi
- checksum

Secret'lar plaintext backup manifest'e yazılmaz.

## Restore
Backup ancak otomatik/doğrulanmış restore prosedürü varsa tamamdır.

Restore drill:
1. izole hedef hazırla
2. DB restore
3. files restore
4. migration/schema/version doğrula
5. critical smoke test
6. checksum/kayıt sayısı kontrolleri
7. sonucu `RestoreRun` olarak kaydet

## Retention
Günlük/haftalık/aylık retention ve harici/offsite kopya hedefi deploy ortamında tanımlanır. Aynı disk üzerindeki tek kopya backup kabul edilmez.

## Queue operasyonu
- retry/backoff
- failed jobs görünürlüğü
- idempotent handler
- poison message sonsuz döngüsünü engelleme
- kontrollü replay

## Scheduler
Aynı kritik job'ın birden fazla node/worker'da yarışmasını lock ile önle. Son çalışma ve hata görünürlüğü sağla.

## Güvenlik hardening
- production debug kapalı
- secure cookie/session
- HTTPS zorunlu
- security headers uygun web katmanında
- least privilege DB user
- Valkey dış ağa açık değil
- file permissions
- admin/credential işlemleri audit

## Incident hazırlığı
Correlation id, audit log ve immutable ledger sayesinde bir finans/stok olayının kaynağı izlenebilmelidir.

## Veri saklama
Log/audit/import payload retention ayrı tanımlanır. Gereksiz kişisel veri süresiz tutulmaz; finansal/business kayıtların yasal saklama gereksinimi ayrıca uygulanır.

## Release
Deploy öncesi:
- CI green
- migration review
- backup
- backward-compatible deploy sırası
- smoke test
- rollback/reversal planı

Destructive schema değişiklikleri doğrudan release ile birlikte tek adımda yapılmaz.

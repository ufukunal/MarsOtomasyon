# Production security ve secret runbook

## Secret sınırı

Production secret değerleri repository, release artifact, deployment metadata, backup manifest veya log içine yazılmaz.

Secret kaynağı:

- `/etc/mars/mars.env` veya eşdeğer secret store,
- dosya sahibi deployment/service yöneticisi,
- önerilen izin `0640`,
- web/worker yalnız gereken değerleri okuyabilir.

Zorunlu production secret grupları:

- `APP_KEY`
- Master/Period PostgreSQL credential
- Valkey password gerekiyorsa
- SMTP credential
- channel credential encryption için kullanılan application key
- `BACKUP_ARCHIVE_PASSWORD`
- off-VDS backup storage credential
- webhook/channel secret'ları

`php artisan operations:security-check` production config guard'larını ve `RUNTIME_DB_USERNAME` rolünün PostgreSQL least-privilege sınırlarını doğrular. Runtime rol SUPERUSER/CREATEDB/CREATEROLE/REPLICATION veya database/schema CREATE yetkisi taşıyorsa deploy green gate başarısız olur.

## APP_KEY / encryption-key recovery

`APP_KEY` kaybı encrypted channel credential'ları ve framework encrypted payload'ları geri döndürülemez hale getirebilir.

Bu nedenle:

1. APP_KEY ayrı güvenli secret vault içinde versiyonlu tutulur.
2. Recovery set manifest APP_KEY değerini içermez.
3. Disaster recovery sorumlusu gerekli key sürümünü recovery set zamanı ile eşleştirir.
4. Restore temporary verification ortamında doğru key yüklenmeden production recovery yapılmaz.
5. Key rotation sırasında eski key recovery materyali retention süresi bitene kadar güvenli vault'ta korunur.
6. Key hiçbir ticket, log, deployment metadata veya Git commit içine kopyalanmaz.

## Service hesapları

En az üç erişim sınırı kullanılır:

- `mars_app`: web/worker runtime DML; database/schema create/drop yok. Active period DB'lerde DML gerekir; restore edilmiş closed archive DB'lerde yalnız read-only erişim verilir ve database-level `default_transaction_read_only=on` uygulanır.
- `mars_migrator`: deploy/migration DDL; normal web process tarafından kullanılmaz.
- `mars_backup`: backup için read-only veri erişimi.

Production process manager web/worker/scheduler için ayrı OS service boundary kullanabilir; secret dosyası yalnız gereken Unix group'a okunur.

## Log güvenliği

`SensitiveDataProcessor` authorization, password, secret, token, credential, API key, cookie/session ve kişisel iletişim alanlarını redact eder.

Harici platform full payload'ları loglanmaz. Unexpected exception logunda correlation id kullanılır; kullanıcıya credential veya raw external response gösterilmez.

## Dosya izinleri

- release dizinleri runtime tarafından yazılamaz,
- yalnız `storage/` ve gerekli cache dizinleri service user tarafından yazılabilir,
- Nginx root yalnız `current/public`,
- `.env`, backup archive, SQL dump ve operasyon dosyaları public root altında bulunmaz.

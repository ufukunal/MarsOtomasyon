# Period migration dağıtımı

Faz 0'ın production sorumluluğu yalnız period DB migration zincirini güvenilir biçimde
çalıştırmaktır. Production release aktivasyonu Faz 11 G-1103 immutable-release
sözleşmesinin sorumluluğudur.

## Komutlar

```bash
php artisan migrate:periods
php artisan migrate:periods --year=2026
php artisan migrate:periods --company=1
php artisan migrate:periods --status
php artisan migrate:periods --pretend
php artisan permissions:sync-company-roles
```

Komut yalnız Master `periods.status in (active, closed)` kayıtlarını dolaşır.
`archived` dönemler otomatik olarak atlanır.

Her period için:

1. Master period kaydı okunur.
2. `PeriodContext::useSystem(company_id, period_id)` ile fiziksel DB seçilir.
3. `database/migrations/period` zinciri çalıştırılır.
4. Başarı sonrası DB'nin son uygulanmış migration adı `periods.schema_version`
   alanına yazılır.
5. Tek DB hata verirse diğerleri raporlanmaya devam eder, ancak komut sonunda
   non-zero exit code döner.

`--pretend` SQL'i uygulatmaz ve `schema_version` değiştirmez.
`--status` repo migration listesi ile DB `migrations` tablosunu karşılaştırır.

Yeni ekran/aksiyon izinleri eklendiğinde period migrationlarından sonra `permissions:sync-company-roles` çalıştırılır. Bu komut demo kullanıcı/şirket seed etmez; mevcut şirketlerde yalnız sistem rol matrisini günceller.

## Migration disiplini

- Period migration'larının `down()` metodu bulunur.
- Uzun veri taşıma migration içine gömülmez; ayrı backfill komutu kullanılır.
- Yeni period DB, `CreatePeriod` sırasında period migration zincirinin tamamını alır.
- Restore edilmiş period uygulamaya açılmadan önce uygun
  `migrate:periods --company=... --year=...` çağrısından geçer.
- Migration öncesinde doğrulanmış Master + period + dosya recovery seti bulunmalıdır.

## Production sınırı

Bu görev deployment scripti, `git pull`, `down/up` veya release symlink değişimi
uygulamaz. Production release sırası ve rollback/forward-fix davranışı Faz 11
G-1103/G-1104/G-1105 kapsamında uygulanır.

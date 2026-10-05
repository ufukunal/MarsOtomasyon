# Arşiv dönem ve geri yükleme

Archived period fiziksel DB'si production sunucudan detach edilmiş, Master `periods` kaydı korunmuş dönemdir. Archived dönem sessizce rapora dahil edilmez; restore/attach gereksinimi açık hata olarak gösterilir.

## Archive detach önkoşulu

Bir period yalnız:

- `status=closed`,
- seçilen recovery set `verified`,
- recovery set period manifestinde aynı `database_name` mevcut,
- recovery set gerçek temporary restore provasından geçmiş

ise detach edilebilir.

Komut:

```bash
php artisan operations:archive-period <period_id> <backup_run_id>
```

Komut varsayılan olarak destructive onay ister. Başarılı olduğunda fiziksel DB kaldırılır ve period `archived` olur. DB drop başarısızsa Master status `closed` durumuna geri çevrilir.

## Archive restore / attach

```bash
php artisan operations:archive-restore <backup_run_id> <period_id>
```

Akış:

1. recovery set archive/checksum doğrulaması,
2. ilgili period SQL dump restore,
3. period migration zinciri,
4. reference data doğrulaması,
5. Master period status `closed`,
6. PostgreSQL `default_transaction_read_only=on`.

Restore edilen dönem `active` yapılmaz ve normal write akışları `PeriodContext::ensureWritable()` tarafından reddedilir.

## Çok dönemli rapor

- `closed` restore edilmiş dönem okunabilir.
- `archived/detached` dönem sessizce atlanmaz.
- kullanıcıya restore gereksinimi bildirilir.

## Disaster recovery

Production recovery için manuel DB komutları yerine `docs/isletim/disaster-recovery.md` izlenir. Verified recovery set olmadan production restore yapılmaz.

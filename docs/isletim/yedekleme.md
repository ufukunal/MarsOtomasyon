# Recovery-set yedekleme ve geri yükleme

Production backup gerçek kaynağı `backup_runs` ve recovery-set manifestidir. Master DB, tüm `active|closed` period DB'ler ve attachment/files aynı recovery set içinde izlenir.

`PrepareBackupSources`, Spatie `backup:run` başlamadan Master'daki period listesini dinamik olarak backup kaynaklarına ekler. Faz 11 katmanı bunun üzerinde checksum, off-VDS kopya ve operasyon geçmişi üretir; paralel ikinci backup sistemi yoktur.

## Hedefler

- `backups`: VDS üzerindeki yerel kopya.
- `backup_external`: S3 uyumlu off-VDS kopya.

Recovery set ancak iki hedefe de yazılmış ve archive checksum değerleri eşleşmişse `done` olur. Restore provası başarılı olmadan `verified` sayılmaz.

## Komutlar

Manuel:

```bash
php artisan operations:backup --trigger=manual
```

Deploy öncesi `DeploymentService` otomatik `deploy` trigger kullanır. Dönem devri state-changing aşamadan önce otomatik `period_carry` trigger kullanır.

## Zamanlama

- 01:00 `backup:clean`
- 01:30 `RunRecoverySetBackupJob('scheduled')` → privileged `operations` queue
- 02:00 `backup:monitor`
- Pazar 05:00 `VerifyLatestRecoverySetBackupJob` → temporary restore provası

Retention: mevcut Spatie backup cleanup politikası.

## Recovery-set manifest

Manifest secret içermez. En az:

- recovery_set_id
- uygulama/version
- Master DB adı
- active/closed period DB listesi
- local/off-VDS archive path, checksum, size
- files dahil bilgisi
- encryption algoritması

taşır.

## Restore provası

```bash
php artisan operations:restore-verify
php artisan operations:restore-verify <backup_run_id>
```

Akış production DB'lerine dokunmadan temporary Master/Period DB'leri oluşturur, restore eder, migration zincirini ve `integrity:all` kontrolünü çalıştırır. Başarı sonrası backup `verified` ve `verified_at` alır.

Archive restore ve disaster recovery için `docs/isletim/disaster-recovery.md` kanoniktir.

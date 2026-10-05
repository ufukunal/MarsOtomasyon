# Disaster recovery ve restore runbook

## Temel kural

Production veritabanının üzerine doğrulanmamış backup kör biçimde restore edilmez.

Recovery set ancak şu zincirden sonra güvenilir kabul edilir:

1. local + off-VDS archive checksum eşleşmesi,
2. temporary restore,
3. Master migration,
4. tüm Period migration,
5. integrity doğrulaması.

## Temporary restore provası

Son recovery set:

```bash
php artisan operations:restore-verify
```

Belirli backup:

```bash
php artisan operations:restore-verify <backup_run_id>
```

İşlem temporary DB'ler oluşturur, dump'ları yükler, temporary Master içindeki period database_name alanlarını temporary DB'lere yönlendirir, migration + `integrity:all` çalıştırır ve temporary DB'leri sonunda siler.

Başarı sonrası:

- `restore_runs.status=verified`
- `backup_runs.status=verified`
- `backup_runs.verified_at`

yazılır.

## Archive detach

Yalnız `closed` period ve restore-provası verified recovery set ile:

```bash
php artisan operations:archive-period <period_id> <backup_run_id>
```

Komut recovery setin ilgili period DB'yi içerdiğini doğrular. Başarılı detach sonrası Master period status `archived` olur ve fiziksel DB kaldırılır.

## Archive restore / attach

```bash
php artisan operations:archive-restore <backup_run_id> <period_id>
```

Akış:

1. checksum doğrula,
2. period SQL dump'ını restore et,
3. period migrations uygula,
4. reference data doğrula,
5. period status → `closed`,
6. PostgreSQL `default_transaction_read_only=on`.

Restore edilen archive period active yapılmaz.

## Production disaster recovery

Production recovery otomatik HTTP/UI aksiyonu değildir.

1. Incident correlation id ve etkilenen release belirlenir.
2. Trafik maintenance/read-only moda alınır.
3. Queue, scheduler ve operations worker durdurulur.
4. Kullanılacak recovery set için `operations:restore-verify <id>` sonucu **verified** olmalıdır.
5. Mevcut bozuk production DB'lerin forensic snapshot'ı alınır.
6. DBA/migrator hesabıyla Master ve period DB'ler recovery-set manifestine göre temiz recovery hedeflerine restore edilir.
7. Attachments/files aynı recovery setten restore edilir.
8. Candidate uygulama ile Master migration + `migrate:periods` uygulanır.
9. `integrity:all`, `operations:security-check`, `operations:smoke`, `operations:health` çalıştırılır.
10. Queue/scheduler/operations worker başlatılır.
11. Health yeşil olmadan trafik açılmaz.

Schema/data problemi varsa otomatik migration `down` zorlanmaz. Forward-fix veya bu verified recovery akışı kullanılır.

## Encryption key recovery

Recovery set secret değerlerini içermez. Restore ortamına doğru `APP_KEY` ve backup archive password ayrı secret vault'tan yüklenir. Yanlış/kayıp key ile production recovery yapılmaz.

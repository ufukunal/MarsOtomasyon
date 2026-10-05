# Go-live cutover ve rollback

## Önkoşullar

Gerçek trafik açılmadan önce DNS/TLS, production secretleri, least-privilege DB rolleri, queue/scheduler/operations servisleri ve off-VDS backup erişimi hazır olmalıdır. Final acceptance/test aşaması ayrıca yeşil olmalıdır.

## Dry-run

```bash
php artisan operations:security-check
php artisan operations:smoke
php artisan operations:health --no-persist
```

Harici kanal bağlantıları bilinçli olarak ayrıca doğrulanır:

```bash
php artisan operations:smoke --external
```

## Cutover sırası

1. Uygulamayı maintenance/read-only moda al.
2. Final recovery set al: `php artisan operations:backup --trigger=manual`.
3. Backup sonucunun checksum + off-VDS doğrulamasını kontrol et.
4. Hazır immutable artifact ile `ops/deploy-release.sh` çalıştır.
5. Master ve bütün active/closed period migrationlarının başarılı olduğunu doğrula.
6. `integrity:all`, security check ve smoke başarılı olmalı.
7. Queue, scheduler ve operations worker restart edilmeli.
8. `operations:health --no-persist` sonucu `healthy` olmalı.
9. Gerekli ise external channel smoke çalıştır.
10. Ancak bütün kapılar yeşilse trafiği aç.

Backup, migration, integrity, security, smoke veya health başarısızsa trafik açılmaz.

## Code rollback

Sorun yalnız kod kaynaklı ve previous release mevcut schema ile uyumluysa:

```bash
php artisan operations:rollback --schema-compatible
```

Bu akış migration down çalıştırmaz.

## Schema veya data problemi

Otomatik migration rollback zorlanmaz. Verified recovery set seçilir ve `docs/isletim/disaster-recovery.md` uygulanır. Restore sonrası integrity + smoke + health yeşil olmadan trafik açılmaz.

## Operasyon geçmişi

Ayarlar > Operasyon Merkezi ekranında deployment, backup, restore verification ve health history izlenir. Destructive production restore/rollback UI üzerinden başlatılmaz.

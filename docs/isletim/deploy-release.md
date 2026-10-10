# Immutable production release runbook

## Dizin yapısı

- `/var/www/mars/releases/<release_id>`: immutable release
- `/var/www/mars/current`: aktif release symlink
- `/var/www/mars/shared/storage`: kalıcı runtime storage
- `/etc/mars/mars.env`: web/normal worker runtime secretleri
- `/etc/mars/mars-operations.env`: privileged operations/deploy secretleri

Release dizini aktive edildikten sonra değiştirilmez.

## Servisler

- `mars-queue.service`: normal `default` queue, runtime app credential
- `mars-scheduler.service`: scheduler
- `mars-operations.service`: `operations` queue, migrator/backup credential

## Release artifact bütünlüğü

Bir sürümü dağıtmadan önce aynı tam 40 karakterli Git SHA için temiz checkout'tan,
uygulama secret'ı olmadan ve ayrı bir artifact dizininde build alın:

```bash
bash ops/build-release-artifact.sh <full-commit-sha> /srv/mars-artifacts/<release-id>
```

Bu işlem `composer install --no-dev`, `npm ci`, `npm run build` ile paket üretir;
`.mars-release-commit` ve SHA-256 dosya manifesti ekler.
Dağıtım betiği **servis veya release dizinini değiştirmeden önce** tam commit
eşleşmesi ile bütün dosya checksum'larını doğrular. Geçersiz/eksik manifest
veya artifact içine yanlışlıkla eklenmiş `.env` işlemi durdurur.

Bu manifest bir **kriptografik imza değildir**: artifact'a yazma izni olan saldırgan
manifesti de değiştirebilir. Artifact storage ve CI yazma erişimi ayrıca
kısıtlanmalıdır.

## Release hazırlama

Artifact en az şunları içermelidir:

- uygulama kodu
- Composer lock/dependency tanımı
- frontend build girdileri veya hazır `public/build`
- migration dosyaları

Repository secret içermez. `.env` artifact içine kopyalanmaz.

Örnek:

```bash
sudo -u mars-deploy ops/deploy-release.sh \
  2026.10.06-001 \
  <commit-sha> \
  /srv/mars-artifacts/2026.10.06-001 \
  <previous-release-id>
```

Script candidate release'i immutable dizine alır ve `operations:deploy` çalıştırır.

## Çakışan dağıtım koruması

`ops/deploy-release.sh` artefact doğrulamasından sonra
`/var/www/mars/.mars-deploy.lock` için `flock` alır.
Aynı anda ikinci dağıtım çalışırsa migration veya release dizinine dokunmadan reddedilir.
Doğrudan `php artisan operations:deploy` çağrıları bu shell kilidini kullanmadığından
normal dağıtım girişi **yalnız** onaylı deploy betiği olmalıdır.

## operations:deploy sırası

1. Operational readiness tabloları henüz yoksa:
   - önce normal `backup:run`,
   - yalnız readiness bootstrap migration,
   - tracked deploy akışına geçiş.
2. Tracked recovery-set backup:
   - Master,
   - tüm active/closed Period DB,
   - attachments/files,
   - local + off-VDS archive,
   - checksum manifest.
3. Master migration — `--database=master --path=database/migrations/master --force` ile **Master alt dizini** açıkça kullanılır.
4. `migrate:periods --force`.
5. Cache/build warmup.
6. `integrity:all`.
7. `operations:security-check`.
8. `operations:smoke`.
9. Operational health pre-activation gate: `status=healthy` değilse (degraded dahil) candidate release aktive edilmez.
10. Candidate release → `current` atomik symlink.
11. Queue restart sinyali.
12. systemd web/queue/scheduler/operations process restart.
13. Post-activation `operations:health` + `operations:smoke`.

Herhangi bir migration veya verification hatası symlink değişiminden önce oluşursa candidate release active olmaz.

Not: Yedek dosyasının yazılması, doğrulanmış restore anlamına gelmez. İlk go-live'dan önce
`operations:restore-verify` ile izole temporary restore kanıtı alınmalıdır.
Mevcut uygulama dağıtımı systemd/symlink temellidir; bir Docker runtime'a
taşınmadan önce bu betiği Docker container içinde çalıştırmayın.

## Rollback

Yalnız mevcut schema ile uyumlu code rollback:

```bash
php artisan operations:rollback --schema-compatible
```

Komut migration `down` çalıştırmaz.

Schema/data riski varsa code rollback yerine verified recovery-set DR runbook'una geçilir.

## Deployment history

Her tracked deployment `deployment_runs` içinde:

- release id
- commit SHA
- actor
- previous release
- durum
- recovery set
- pre-activation health
- hata özeti

ile izlenir.

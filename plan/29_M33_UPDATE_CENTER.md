# M33 — Mars Update Center / Release Management

## Amaç

Mars'ın ana uygulama sürümlerini yönetim yüzeyinden güvenli biçimde kontrol etmek; paket güveni, staging, atomik aktivasyon, health-check ve rollback zincirini web prosesine gereksiz ayrıcalık vermeden kurmak.

## Güvenlik kararı

Web uygulaması kendi kaynak dosyalarını doğrudan ezmez ve manifest içinden shell komutu çalıştırmaz. Release zinciri imzalı manifest, deterministik paket, yedek, staging, migration gate, sağlık kontrolü, atomik aktivasyon ve rollback üzerine kurulacaktır.

Private signing key repository, veritabanı, uygulama `.env` dosyası veya web process içinde tutulmaz. Mars yalnız public doğrulama anahtarını taşır.

## Slice A — Trust foundation / check-only

- `MARS_VERSION` ile kurulu sürüm kimliği
- stable/beta/development release kanalı
- allowlist içindeki HTTPS manifest endpoint'i
- redirect takip etmeyen manifest fetch
- fail-closed allowlist manifest schema v1
- RSA/SHA-256 detached signature doğrulaması; minimum 2048-bit RSA public key
- paket SHA-256 metadata doğrulaması
- strict SemVer current/latest/min-app karşılaştırması
- strict RFC3339 release timestamp doğrulaması
- malformed/non-object JSON ve eksik trust config için fail-closed davranış
- yalnız platform admin'e açık Güncelleme Merkezi
- check-only davranışı: paket indirme, veritabanı değişikliği, uygulama dosyası değişikliği ve shell çalıştırma yok

## Slice B — Package staging + verification

- manifest verification başarılı olmadan download yok
- HTTPS + exact-host allowlist + explicit redirect policy
- timeout, response-size limiti ve stream download
- kontrollü temporary/staging path ve partial cleanup
- SHA-256, expected size ve gerekiyorsa package signature doğrulaması
- manifest/package version ve package identifier consistency
- archive extraction varsa zip-slip/path traversal, absolute path, `..`, symlink/hardlink escape ve special-file blokları
- extraction size/file-count limitleri
- release staging uygulama root'u dışında
- update lifecycle state modeli ve geçiş testleri
- distributed/advisory lock ve stale-lock recovery
- update audit/history kaydı; credentials/private key saklama yok
- UI'da current/latest/verified/staged/last failure görünümü
- production release switch yok

## Slice C — Atomic updater / deploy agent

- ayrı CLI/deploy-agent privilege boundary
- release directories + atomic `current` pointer switch
- arbitrary shell input/manifest command execution yok
- apply preflight: lock, version re-check, staged trust re-check, disk/writable/binaries/DB/Valkey/queue/scheduler/safety state
- pre-update verified `.marsbak` backup; backup başarısızsa apply başlamaz
- M23 recovery/maintenance mekanizmalarının reuse edilmesi
- queue drain/stop, scheduler pause ve outbound side-effect koordinasyonu
- migration preflight ve backward-compatible expand/contract policy
- release hazırlama → dependency/config/cache → migration → atomic switch → restart/reload → health-check
- health failure halinde önceki release pointer'ına rollback
- DB rollback güvenli değilse audit/state içinde explicit degraded rollback durumu

## Slice D — Production hardening / evidence

- update CLI, dry-run, status ve rollback command
- audit view
- production runbook ve least-privilege deployment permissions
- interrupted update / power-loss recovery
- stale state/lock recovery
- malformed package, checksum/signature mismatch, migration failure, health-check failure, rollback failure, disk-full, network timeout ve concurrent update senaryoları
- secrets redaction
- M33 evidence dokümanı ve gerekiyorsa roadmap/README güncellemesi

## Exit gate

Her slice için PR exact-head Foundation `quality`, `postgres-tests`, `browser-smoke`, `security` 4/4 green olmadan merge yapılmaz. Merge sonrası exact `main` SHA için aynı Foundation 4/4 doğrulanmadan slice kapanmaz.

M33 ancak Slice D merge edilip final exact-main Foundation 4/4 green olduğunda business/code milestone complete sayılır.

# M33 — Mars Update Center / Release Management

## Amaç

Mars'ın ana uygulama sürümlerini WordPress benzeri basit bir yönetim deneyimiyle, kurumsal deployment güvenliğini bozmadan dağıtmak.

## Güvenlik kararı

Web uygulaması kendi kaynak dosyalarını doğrudan ezmez ve manifest içinden shell komutu çalıştırmaz. Release zinciri imzalı manifest, deterministik paket, yedek, staging, migration gate, sağlık kontrolü, atomik aktivasyon ve rollback üzerine kurulacaktır.

Private signing key repository, veritabanı, uygulama `.env` dosyası veya web process içinde tutulmaz. Mars yalnız public doğrulama anahtarını taşır.

## Slice A — Trust foundation

- `MARS_VERSION` ile kurulu sürüm kimliği
- stable/beta/development release kanalı
- HTTPS manifest endpoint'i
- fail-closed allowlist manifest schema v1
- RSA/SHA-256 detached signature doğrulaması
- paket SHA-256 metadata doğrulaması
- minimum PHP ve minimum Mars sürüm uyumluluk hesabı
- yalnız platform admin'e açık Güncelleme Merkezi
- check-only davranışı: paket indirme, veritabanı değişikliği, uygulama dosyası değişikliği ve shell çalıştırma yok

## Sonraki dilimler

### Slice B — Deterministik release üretimi
Foundation 4/4 green exact SHA'dan immutable release archive, built frontend assets, Composer vendor seti, manifest ve detached signature üretimi.

### Slice C — Güvenli staging
Allowlisted package host, boyut/content-type limitleri, SHA-256 kontrolü ve ayrı release dizinine staging. Laravel web process yalnız dar yetkili updater-agent protokolünü çağırır.

### Slice D — Aktivasyon
Safety backup, maintenance mode, backward-compatible migration gate, `optimize`, health/smoke doğrulaması, atomik `current` switch ve queue worker restart.

### Slice E — Rollback / audit
Kod release rollback'i, expand-contract migration politikası, update history ve immutable audit kaydı.

### Slice F — Kanallar / otomasyon
Stable/beta/development politikaları ve opsiyonel bakım penceresi tabanlı otomatik patch/security update.

## Exit gate

M33 ancak gerçek signed release paketinin staging → backup → migration → health → atomic activation → rollback senaryosu production-benzeri ortamda kanıtlandığında DONE olabilir. Slice A tek başına M33'ü tamamlamaz.

# Docker UAT hazırlığı — dönemli MarsProject

Bu bileşen kaynak kod hazırlığıdır; production deployment değildir.
Uygulamanın yerleşik PHP HTTP sunucusu yalnız UAT amaçlıdır. Host tarafında
127.0.0.1:18080 dışında port açılmaz. Kalıcı PostgreSQL/Valkey sunucuları PROD VM
üzerinde korunur; bu Compose herhangi bir DB sunucusu kurmaz.

- Ayrı ve kısıtlı UAT PostgreSQL Master/Period DB ile Valkey ACL önceden doğrulanır.
- /etc/mars/uat.env Git deposuna veya image içine eklenmez. UAT env dosyasında
  MARS_UAT_ISOLATION_CONFIRMED=YES, DB_USERNAME=mars_test,
  DB_MASTER_DATABASE=mars_test_master, REDIS_USERNAME=mars_test,
  REDIS_PREFIX=mars:test: ve yalnız test parolaları bulunmalıdır.
- ops/uat-preflight.sh yalnız yapılandırmayı okur; şema, DB, cache veya Docker
  servisini değiştirmez. ACL, ayrıcalık ve test verisi doğrulaması yerine geçmez.
- Kullanım öncesi: bash ops/uat-preflight.sh /etc/mars/uat.env
- Yalnız veri izolasyonu ve yetkiler doğrulandıktan sonra:
  docker compose -f compose.uat.yml up -d --build web
- Queue ve scheduler yalnız ayrı onay ve test fixture'ları hazır olunca
  --profile workers ile açılabilir.
- Master/Period migration'ları veya CreatePeriod işlemleri otomatik tetiklenmez.
- Mevcut kurulum sihirbazı CREATE DATABASE ayrıcalığına bağlıdır. Web runtime
  hesabına CREATEDB verilmez; ayrı yetkili provisioning mekanizması yazılmalıdır.
- PDF/etiket için Chromium/Puppeteer bu UAT image'ında henüz doğrulanmamıştır.
- Production go-live için PHP-FPM, reverse proxy, TLS, health, backup/restore,
  kullanıcı kabulü ve browser printing ayrıca zorunludur.

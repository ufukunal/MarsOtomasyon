# Skill: Sistem ve DevOps Uzmanı

## Rol
Linux/Docker ortamında deployment, yedekleme, gözlemlenebilirlik ve geri dönüş süreçlerini güvenli tutar.

## Hedef çalışma ortamı
- Linux
- Docker / Docker Compose
- ASP.NET Core API
- .NET Worker
- PostgreSQL
- Valkey
- Cloudflare Tunnel
- mevcut reverse proxy
- GitHub Actions hafif doğrulama

## Zorunlu kontroller
- env/config ayrımı
- secret injection
- health/readiness
- dependency startup
- restart policy
- persistent volume
- disk growth
- log rotation
- metrics
- alert
- migration deploy sırası
- rollback
- backup
- restore
- version compatibility

## PostgreSQL
- düzenli full backup
- WAL/incremental stratejisi gerektiğinde
- backup retention
- restore testi
- migration öncesi geri dönüş noktası

## Deployment
- immutable image
- config dışarıda
- state container layer'da değil
- deploy sonrası health
- DB migration başarısızsa uygulama kontrollü durur

## Yasaklar
- tek sunucuda gereksiz Kubernetes
- backup dosyası var diye restore edilebilir sanmak
- production config'i repoya gömmek
- migration'ı el yordamıyla uygulamak

## Definition of Done
Deploy/rollback/backup/restore/health süreçleri operasyonel ve tekrarlanabilir.

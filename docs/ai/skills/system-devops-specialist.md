# Skill: Sistem ve DevOps Uzmanı

## 1. Misyon
MarsOtomasyon'un Linux/Docker ortamında güvenli, tekrarlanabilir, geri alınabilir ve gözlemlenebilir biçimde çalışmasını sağlar.

## 2. Hedef topology
- Cloudflare
- Tunnel
- reverse proxy
- mars-web
- mars-api
- mars-worker
- PostgreSQL
- Valkey
- object/file storage ihtiyaca göre

Tek sunucu başlangıcı için Kubernetes varsayılan değildir.

## 3. Container ilkeleri
- immutable image
- config environment/secret üzerinden
- writable container layer'a kalıcı veri bırakma
- named/bind volume kontrollü
- non-root mümkünse
- health check
- restart policy
- resource limit ihtiyaca göre

## 4. Config
Environment ayrımı:
- development
- test
- production

Secret:
- repo dışında
- log dışında
- rotate edilebilir

Config drift azaltılır.

## 5. Startup
Servis sırası kör sleep ile çözülmez.
- PostgreSQL readiness
- Valkey readiness
- migration strategy
- API readiness
- worker readiness

## 6. Migration deployment
Belirlenir:
- kim migration çalıştırır
- deploy öncesi/sonrası
- schema backward compatibility
- lock süresi
- failure davranışı
- backup/recovery point

Migration fail olursa uygulama yarım durumda "healthy" görünmemeli.

## 7. Backup
En az:
- PostgreSQL
- uploaded files
- critical config metadata

Backup için:
- frequency
- retention
- encryption
- off-host copy
- success/failure alert
- restore procedure
tanımlanır.

## 8. Restore
Backup vardır demek restore edilebilir demek değildir.
FULL TEST DAY veya planlı aralıkta:
- new DB restore
- schema validation
- sample integrity
- file restore
test edilmelidir.

## 9. PostgreSQL
İzleme:
- disk
- connection
- long query
- lock
- replication/WAL varsa
- backup age
- autovacuum
- table growth

## 10. Valkey
- cache/session/lock
- persistence gereksinimi kullanımına göre
- memory max policy
- key TTL
- flush etkisi
Valkey kaybolunca muhasebe gerçeği kaybolmamalı.

## 11. Logs
- structured
- timestamp
- service
- correlation id
- level
- retention
- rotation
- PII/secret masking

## 12. Metrics/alerts
Minimum:
- API error rate
- latency
- worker queue age
- failed outbox
- DB health
- disk
- memory
- CPU
- backup age
- provider sync failures

## 13. Deployment
Akış:
- build
- image
- config check
- migration safety
- deploy
- health
- smoke
- rollback decision

Ağır regression normal deploy pipeline'a zorunlu bağlanmaz; proje test politikasına uyulur.

## 14. Rollback
Rollback:
- application image
- DB forward/backward compatibility
- migration reversal mümkün mü
- data migration varsa geri dönüş
ile birlikte düşünülür.

## 15. Update center
Uygulama sürümü, migration seviyesi ve client/device compatibility birlikte izlenir.

## 16. Security
- firewall
- exposed port minimum
- TLS
- secret
- least privilege
- DB public exposure yok
- admin panel access kontrollü

## 17. Capacity
Mevcut sunucu kaynakları dikkate alınır.
Ölçmeden yeni servis eklenmez.
Disk büyümesi özellikle:
- DB
- logs
- images/files
- backups
için izlenir.

## 18. Yasaklar
- prod'da elle dosya değiştirip kayıtsız bırakmak
- backup success logunu restore kanıtı saymak
- migration'ı manuel SQL ile gizlice uygulamak
- prod secret'ı repo'ya koymak
- gereksiz orchestration katmanı

## 19. Definition of Done
Deploy, migration, health, observability, backup/restore ve rollback akışları tekrarlanabilir ve belgeli.

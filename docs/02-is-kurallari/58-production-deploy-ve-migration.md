# Production deploy ve migration

## Release

Immutable release dizini:
1. kod/dependency/build hazırlanır,
2. config doğrulanır,
3. maintenance/read-only gerekirse açılır,
4. backup alınır,
5. Master migration,
6. migrate:periods tüm kayıtlı period DB'ler,
7. cache/build warmup,
8. queue worker restart,
9. health + smoke + integrity,
10. current symlink atomik geçiş,
11. trafik açılır.

Başarısız release current olmaz.

## Migration

Bir period migration hata verirse deploy failed.

Destructive migration:
- aynı release'te geri dönüşü imkansız veri kaybı üretmemeli,
- expand/migrate/contract yaklaşımı tercih edilir,
- rollback riskliyse forward-fix veya backup restore.

## Queue / scheduler

- web request'ten ayrı process,
- process manager,
- scheduler heartbeat,
- queue lag ve failed jobs health'e dahil.

## Production config

- debug=false,
- HTTPS zorunlu,
- public root dışında uygulama dosyası servis edilmez,
- cache/session/queue configuration production'a göre açıkça doğrulanır.

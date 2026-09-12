# M33 — Mars Update Center / Release Management

## Amaç

Mars'ın ana uygulama sürümlerini yönetim yüzeyinden güvenli biçimde kontrol etmek; paket güveni, staging, atomik sayılabilecek Docker Compose aktivasyonu, health-check ve rollback zincirini web prosesine host ayrıcalığı vermeden kurmak.

## Güvenlik kararı

Web uygulaması kendi canlı kaynak dosyalarını doğrudan ezmez, Docker socket'e erişmez ve manifest içinden shell komutu çalıştırmaz. Private signing key repository, veritabanı, `.env` veya web process içinde tutulmaz; Mars yalnız public doğrulama anahtarını kullanır.

Güncelleme iki privilege boundary ile çalışır:

1. Laravel kontrol düzlemi manifesti doğrular, paketi güvenli staging alanına indirir/açar ve update lifecycle ledger'ını yönetir.
2. `deploy/mars-update-agent.sh` yalnız host üzerinde, sabit allowlist operasyonlarıyla Docker Compose aktivasyonu ve rollback yapar. Manifestten komut, servis adı veya shell argümanı alınmaz.

## Slice A — Trust foundation / check-only — tamamlandı

- `MARS_VERSION` kurulu sürüm kimliği
- stable/beta/development kanalı
- allowlist içindeki HTTPS manifest endpoint'i
- redirect takip etmeyen fetch
- fail-closed manifest schema v1
- RSA/SHA-256 detached signature, minimum 2048-bit RSA
- package SHA-256 metadata
- strict SemVer ve RFC3339 doğrulaması
- platform-admin-only Güncelleme Merkezi

## Slice B — Package staging + verification — tamamlandı

- manifest doğrulanmadan package download yok
- package URL exact-host HTTPS allowlist'inden tekrar geçirilir
- redirect kapalı, timeout ve maksimum byte limiti uygulanır
- download `.partial` dosyasına stream edilir
- SHA-256 doğrulanmadan package finalize edilmez
- ZIP extraction manuel yapılır; absolute path, `..`, backslash path, NUL, symlink ve special-file girdileri bloklanır
- extraction byte ve file-count limitleri vardır
- gerekli release dosyaları (`artisan`, `composer.json`, `Dockerfile.production`, `docker-compose.production.yml`) doğrulanır
- `update_runs` ve `update_release_artifacts` PostgreSQL ledger'ı vardır
- request-key idempotency ve PostgreSQL advisory/row locks kullanılır
- UI current/latest/staged/history/failure durumlarını gösterir

## Slice C — Host deploy agent / activation — tamamlandı

- web container'a Docker socket verilmez
- apply isteği yalnız ledger'da `apply_requested` üretir
- host agent işi atomik claim eder
- canlı image ID'leri run'a özel rollback tag'leriyle korunur
- candidate release ayrı host release dizinine alınır
- candidate Docker image'ları canlı container'lara dokunmadan önce build edilir
- `BackupManager` ile şifreli `.marsbak` oluşturulur ve doğrulanır; backup başarısızsa migration başlamaz
- `ProductionCandidateGate`, PostgreSQL ve Valkey health preflight uygulanır
- shared Recovery Mode açılarak HTTP mutation, async/scheduler mutation ve outbound side-effect zinciri bloke edilir
- migration candidate image üzerinden `--force` ile çalışır
- app/worker/scheduler/web force-recreate ile aynı Compose project üzerinde aktive edilir
- host-local HTTP health gate geçmeden run tamamlanmaz
- başarılı health sonrası Recovery Mode kapatılır

## Slice D — Rollback / interruption hardening — tamamlandı

- update state machine ileri geçişleri fail-closed doğrular
- migration veya activation sonrası hata pre-update backup + rollback image zincirini tetikler
- rollback önce önceki image tag'lerini geri bağlar, worker/scheduler'ı durdurur, eski app ile doğrulanmış `.marsbak` restore eder, servisleri tekrar başlatır ve health gate çalıştırır
- database restore update ledger'ı da geçmiş zamana döndürdüğü için host-only reconciliation adımı terminal `rolled_back` kanıtını tekrar yazar
- manuel rollback yalnız tamamlanmış run üzerinden platform admin tarafından kuyruğa alınabilir
- agent `flock` ile aynı hostta eşzamanlı çalışmayı engeller; DB claim `SKIP LOCKED` ile ikinci koordinasyon katmanıdır
- failure code/message normalize edilerek ledger'da tutulur; secret veya credential manifest/run metadata'ya yazılmaz

## Host kurulumu

Güncelleme motoru ilk kez normal deployment ile bu sürüme geçirildikten sonra hostta agent çalıştırılır. Örnek tek-sefer çalışma:

```bash
sudo MARS_UPDATE_BASE_DIR=/opt/MarsOtomasyon \
  MARS_UPDATE_HEALTH_URL=http://127.0.0.1:8080/login \
  /opt/MarsOtomasyon/deploy/mars-update-agent.sh
```

Bir timer/cron yalnız bu sabit scripti periyodik çalıştırmalıdır. Web kullanıcısına Docker socket, sudo veya host shell verilmez. Agent kurulduktan ve production backup gate gerçekten hazır olduktan sonra `.env.production` içinde `MARS_UPDATE_AGENT_ENABLED=true` yapılır.

Tailscale production override kullanılıyorsa agent'a örneğin:

```bash
MARS_UPDATE_COMPOSE_FILES=docker-compose.production.yml:docker-compose.tailscale.yml
```

verilir. Candidate package aynı compose dosyalarını içermelidir.

## Operasyon akışı

1. Platform admin **Güncellemeleri Güvenli Kontrol Et**.
2. Uyumlu yeni release için **Paketi Doğrula ve Stage Et**.
3. `staged` run için **Kurulumu Kuyruğa Al**.
4. Host agent candidate build → verified backup → Recovery Mode → migration → activation → health-check yapar.
5. Başarı `completed`; post-migration hata otomatik rollback; tamamlanmış run için manuel **Rollback** kullanılabilir.

## Fail-closed kuralları

- production backup gate sorunluysa apply başlamaz
- agent kapalıysa UI apply/rollback kabul etmez
- staged checksum hostta ikinci kez doğrulanır
- candidate archive içinde symlink kabul edilmez
- host agent manifest komutu çalıştırmaz
- rollback image veya backup kanıtı yoksa sessizce devam edilmez
- rollback health-check başarısızsa recovery olayı operatör müdahalesi gerektirir

## Exit gate

M33 ancak exact PR head Foundation `quality`, `postgres-tests`, `browser-smoke`, `security` 4/4 green ve merge sonrası exact `main` SHA aynı 4/4 green olduğunda business/code milestone complete sayılır. Production-final ayrıca gerçek offsite backup, rotate edilmiş recovery key ve production deploy gate gerektirir.

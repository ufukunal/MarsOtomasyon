# 29 — M0 Altyapı Uygulama Sırası — Foundation Execution Contract

Bu belge `M0 — Repository / Laravel / PostgreSQL / CI Foundation` için **uygulama sırasını** tanımlar.

Amaç altyapıyı tek büyük bootstrap commit'i olarak kurmak değil; her biri ayrı doğrulanabilen küçük foundation gate'leriyle ilerlemektir.

M0 sırasında business modülü geliştirilmeyecek. Cari, ürün, stok, satış, alış, marketplace veya CRM ekranı yazmak M0 kapsamı değildir.

Her gate tamamlandığında:

`kod/config → lokal komut/smoke → PostgreSQL/Valkey doğrulaması where applicable → test → atomic commit`

Bir gate yeşil olmadan sonraki gate başlamaz.

---

# 1. M0 standart toolchain

## Runtime
- PHP: **8.5.x**, production ve CI aynı minor family
- Laravel: **13.x**
- PostgreSQL: **18.x**, güncel security/bugfix minor; ilk CI baseline **18.6**
- Valkey: **9.1.x**, ilk CI baseline **9.1.1**
- Node.js: **24 LTS**
- package manager: **npm**, `package-lock.json` zorunlu
- frontend build: Laravel Vite baseline

Patch/security sürümleri lockfile/CI değişikliğiyle kontrollü güncellenebilir. Major/minor family değişikliği ayrı plan/compatibility review ister.

## PHP kalite/test
- Laravel Pint — code style
- Larastan 3 / PHPStan 2 — static analysis
- PHPStan başlangıç seviyesi: **8**, baseline/ignored-error dosyası yok
- Pest 5 — test runner
- Pest Laravel plugin — Laravel test integration
- Pest Browser plugin + Playwright — gerçek browser/E2E
- PHPUnit 13 Pest'in altında çalışır; ayrı ikinci test stili oluşturulmaz

Rector, Telescope, Horizon, Octane, Scout, Pennant, Sanctum, Fortify, Livewire gibi paketler gerçek consumer milestone'a gelmeden M0'a eklenmez.

## Frontend
- Blade + Alpine için minimal Vite skeleton
- Tailwind/başka CSS framework seçimi onaylı V16.3 uygulama ihtiyacına göre yapılır; M0 business UI üretmez
- `npm ci` lockfile dışına çıkmaz
- browser test dependency'si Playwright lockfile ile pinlenir

---

# 2. M0 commit/gate sırası

## M0.0 — Repository hygiene ve toolchain freeze

### Yapılacaklar
- mevcut `plan/` korunur
- root `.editorconfig`
- `.gitattributes`
- `.gitignore`
- PHP 8.5 requirement
- Node 24 engine/version contract
- npm package-manager metadata
- line ending UTF-8/LF standardı
- Composer/npm dependency policy
- `.env.example` secret policy notu

### Yasak
- gerçek secret/token
- production credential
- hosta özel path/IP
- gereksiz Docker orchestration
- business module migration

### Gate
Repo yalnız text/config seviyesinde tutarlı olmalı; secret scan'de gerçek credential bulunmamalı.

---

## M0.1 — Temiz Laravel 13 bootstrap

### Yapılacaklar
- Laravel 13 application skeleton repository root'a yerleşir
- `composer.json` ve `composer.lock`
- `artisan`, `bootstrap/`, `config/`, `public/`, `resources/`, `routes/`, `storage/`, `tests/`
- default SQLite kullanımı kaldırılır
- application name/config MarsOtomasyon'a uyarlanır
- `.env.example` PostgreSQL + Valkey placeholders kullanır
- Laravel'in default welcome/demo içeriği yalnız smoke ihtiyacı kadar tutulur

### Dependency ilkesi
M0 sonunda Composer runtime dependency seti mümkün olduğunca Laravel default'una yakın tutulur.

Dev dependency olarak yalnız gerçekten kullanılan kalite/test paketleri eklenir.

### Gate
- `composer validate --strict`
- `composer install --no-interaction`
- `php artisan --version`
- `php artisan about` secret sızdırmadan çalışır

---

## M0.2 — PostgreSQL 18 foundation

### Config
- `DB_CONNECTION=pgsql`
- SQLite test fallback yok
- CI ve local test gerçek PostgreSQL kullanır
- persistent timestamp yaklaşımı `timestamptz`
- DB default isolation `READ COMMITTED`

### PostgreSQL extension migration
İlk infrastructure migration yalnız gerçekten kilitli extension'ları açar:
- `pg_trgm`

FTS PostgreSQL built-in capability olarak kullanılır. Başka extension gerçek use-case olmadan eklenmez.

### DB standardı
- money/qty/cost ileride `NUMERIC(20,6)`
- rate `NUMERIC(20,10)`
- binary float finans authority olmayacak
- JSONB kritik finans/stok alanlarının yerine kullanılmayacak
- tenant/company rule M1'de başlar

### Test
- DB connection smoke
- migration fresh
- rollback/refresh
- `pg_trgm` installed assertion
- PostgreSQL version major = 18 assertion

### Gate
`php artisan migrate:fresh --force` gerçek PostgreSQL 18 üzerinde temiz çalışmadan M0.3'e geçilmez.

---

## M0.3 — Valkey / cache / queue coordination foundation

### Runtime kararı
Laravel Redis-compatible connection **PhpRedis extension** üzerinden Valkey'e bağlanır.

Valkey:
- cache
- queue
- rate-limit
- transient lock
- session when enabled
amaçlıdır.

Business truth değildir.

### Config
- connection adı/config semantiğinde `valkey` açıkça belgelenir
- cache/queue/session için ayrı namespace/prefix
- queue `after_commit` davranışı aktif
- timeout/retry değerleri config/env'den gelir
- secret UI/log'a yazılmaz

### Test
- `PING`
- cache write/read/forget
- queue connection config smoke
- DB transaction rollback sonrası business truth'un Valkey'de kalmadığını doğrulayan foundation test where applicable

### Gate
PostgreSQL kapalıyken uygulama business posting yapamaz; Valkey kaybı business ledger kaybı yaratmaz.

---

## M0.4 — Foundation namespace ve modular monolith iskeleti

M0 boş modül klasörleri üretmez.

İlk gerçek foundation yapısı:

```text
app/
  Foundation/
    Clock/
    Correlation/
    Features/
    Idempotency/
    Outbox/
    Providers/
  Modules/
    Core/
```

Diğer `Accounts`, `Catalog`, `Inventory`, `Sales` vb. klasörler ilgili milestone başladığında oluşturulur.

### Kurallar
- `Foundation` business modülü değildir
- `Foundation`, `Modules` içindeki model/business state'e bağımlı olmaz
- modüller birbirinin tablolarını keyfi mutate etmez
- generic repository/interface/plugin framework kurulmaz
- Eloquent gerçek kullanımda uygunsa doğrudan kullanılabilir

### Feature registry
M0'da code/config based canonical `FeatureKey` contract hazırlanır.

DB'de company-feature tablosu gerçek per-company rollout ihtiyacına kadar oluşturulmaz.

### Provider registry convention
Yalnız naming/typed metadata contract:
- provider key
- family
- capability key
- version/docs metadata şekli

Gerçek marketplace/kargo provider tablosu M17/M28 consumer'ına kadar gereksiz yere kurulmaz.

### Gate
Architecture testleri en az:
- `Foundation` → `Modules` dependency yasağı
- forbidden debug/security patterns
- future module dead menu/route oluşmaması
korumalarını içerir.

---

## M0.5 — Clock + Correlation + Idempotency foundation

### Clock
Business code doğrudan dağınık `now()` çağrılarına bağımlı kalmayacak şekilde test edilebilir Clock service oluşturulur.

- system clock
- test/frozen clock
- UTC instant storage
- business timezone dönüşümü ileride Company setting üzerinden

### Correlation
HTTP request başında correlation ID belirlenir.

- geçerli inbound ID kabul edilebilir
- geçersiz/aşırı uzun değer reject/replace edilir
- yoksa ULID üretilir
- log context'e eklenir
- job/outbox context'e taşınabilir
- response header'da döndürülür

### Idempotency
M0 minimal infrastructure:
- `idempotency_records` migration
- scope/key/fingerprint/status metadata
- aynı key + aynı fingerprint retry-safe
- aynı key + farklı fingerprint conflict
- company/public API scope M1/M20'de genişletilir

Idempotency key business document number değildir.

### Gate
- frozen clock deterministic test
- correlation request→response test
- invalid correlation sanitization
- idempotency same/different fingerprint tests

---

## M0.6 — Transactional Outbox skeleton

Outbox business event authority değildir; committed DB değişikliğinin external async yan etkisini taşır.

### Minimal entity/migration
`outbox_messages` en az:
- internal id
- event/message id unique
- event name
- schema version
- semantics
- payload JSONB
- correlation id
- company id nullable until M1 where appropriate
- available/occurred timestamps
- attempts
- lease metadata
- published/completed timestamp
- redacted last-error metadata

### Contract
Event name örneği:
- `system.smoke.v1`

M0'da yalnız smoke event kullanılır; hayali Sales/Stock event implementation'ı yapılmaz.

### Zorunlu test
1. aynı DB transaction içinde source row + OutboxMessage oluştur
2. transaction commit → ikisi de var
3. transaction rollback → ikisi de yok
4. duplicate message/effect identity ikinci kayıt üretmiyor
5. payload/log secret redaction

### Queue sınırı
External HTTP call DB transaction içinde yapılmaz.

Queue job enqueue/dispatch commit sonrasıdır. Outbox PostgreSQL'de durable source'tur; Valkey queue kaybı replay/recovery ile giderilebilir olmalıdır.

---

## M0.7 — Health / readiness / observability baseline

### Endpointler
- Laravel liveness `/up`
- readiness `/health/ready`

Readiness en az:
- PostgreSQL `SELECT 1`
- Valkey `PING`
kontrol eder.

Public response:
- `ok | unavailable`
- correlation id

gibi minimum bilgi verir. DB hostname, credential, stack trace veya provider secret göstermez.

Detaylı admin health ekranı M17/M23 ihtiyacına kadar kurulmaz.

### Logging
- structured/contextual logging
- correlation id
- job/outbox message id
- secret/PII redaction convention

Business audit log değildir; AuditEntry M1'de business owner olarak eklenir.

### Gate
- live 200
- ready 200 when DB+Valkey healthy
- ready 503 on dependency failure
- response secret-free

---

## M0.8 — Test stack ve quality scripts

### Pest 5
Test sınıfları:
- Unit
- Feature
- Integration/PostgreSQL
- Architecture
- Browser

### Browser
Pest Browser + Playwright kullanılır.

İlk browser smoke:
- `/up`
- root/app shell placeholder route
- JS error yok
- console error yok

V16.3 gerçek UI acceptance M1'den itibaren büyür.

### Composer scripts
Canonical isimler:
- `composer format`
- `composer format:check`
- `composer analyse`
- `composer test`
- `composer test:unit`
- `composer test:integration`
- `composer test:browser`
- `composer ci`

`composer ci` en az format-check + static analysis + full non-browser suite çalıştırır; CI job'ları daha ayrıntılı paralel olabilir.

### Static analysis
Larastan/PHPStan:
- level 8
- no baseline
- ignored error list varsayılan yok
- new error suppress yerine code/type düzeltme

### Architecture tests
Pest:
- Laravel/strict/security presetleri uygun olduğu ölçüde
- `dd`, `dump`, `ray`, `eval` gibi production path yasakları
- `Foundation` dependency sınırı

### Dependency security
- `composer audit`
- `npm audit --audit-level=high`

Audit findings sessiz ignore edilmez; false positive ise gerekçeli/expiry'li exception gerekir.

---

## M0.9 — GitHub Actions CI

CI dört açık required check üretir:

### `quality`
- Composer validate/install
- npm ci
- Pint check
- Larastan/PHPStan
- frontend build

### `postgres-tests`
- PostgreSQL **18.6** service
- migration fresh
- Pest unit/feature/integration
- pg_trgm assertion

### `browser-smoke`
- Node 24 LTS
- Playwright browser install
- PostgreSQL + Valkey dependencies
- application boot
- Pest Browser smoke

### `security`
- composer audit
- npm high/critical audit
- secret/basic dependency checks

Valkey CI baseline: **9.1.1**.

CI'da SQLite kullanılmaz.

### Cache
Composer/npm cache yalnız dependency indirme hızlandırmak için kullanılır; test DB veya generated business state cache'lenmez.

### Gate
Dört job yeşil olmadan M0 complete değildir.

---

## M0.10 — Branch protection ve fresh-clone acceptance

### Main protection
Mümkün olan GitHub plan/izinleri ölçüsünde:
- force push kapalı
- branch delete kapalı
- required checks: `quality`, `postgres-tests`, `browser-smoke`, `security`

PR zorunluluğu ayrıca ekip workflow kararıdır; mevcut tek-geliştirici/direct-main çalışma şekli sessizce değiştirilmez.

GitHub required-check enforcement direct-main ile teknik olarak uygulanamıyorsa durum `repo-operation blocker` olarak kaydedilir; CI yine tamamlanmış iş gate'idir.

### Final fresh-clone acceptance
Temiz checkout üzerinde sırasıyla:

```text
composer install
npm ci
copy .env.example → test/local env
php artisan key:generate
php artisan migrate:fresh --force
npm run build
composer format:check
composer analyse
composer test
composer test:browser
```

çalışır.

### M0 exit
- PostgreSQL 18 gerçek
- Valkey gerçek
- SQLite yok
- CI green
- no secret
- no business module shortcut
- Outbox rollback/commit doğrulanmış
- idempotency/correlation foundation testli
- health/readiness testli
- fresh clone reproducible

Bu gate geçmeden **M1 başlamaz**.

---

# 3. İlk uygulama commit sırası

Önerilen atomic commit zinciri:

1. `chore: bootstrap Laravel 13 toolchain`
2. `chore: configure PostgreSQL 18 foundation`
3. `chore: configure Valkey coordination`
4. `refactor: establish foundation module boundaries`
5. `feat: add clock correlation and idempotency foundation`
6. `feat: add transactional outbox skeleton`
7. `feat: add health readiness baseline`
8. `test: establish Pest Larastan and browser gates`
9. `ci: add PostgreSQL Valkey quality workflows`
10. `chore: enforce M0 repository protection gates`

Her commit independently anlaşılabilir ve mümkün olduğu ölçüde green olmalıdır. Bir sonraki commit öncekinin kırığını saklamak için kullanılmaz.

---

# 4. M0 sırasında özellikle yapılmayacaklar

- Cari/Ürün/Stok/Satış business tabloları
- marketplace adapter implementation
- SMS/e-mail/WhatsApp provider implementation
- kargo API implementation
- OCR implementation
- CRM
- BI
- ProductFamily fiziksel tabloları
- generic plugin manager
- EAV/custom-field engine
- generic BPM/workflow engine
- Kubernetes/microservice scaffolding
- Elasticsearch/Meilisearch
- Horizon/Telescope/Pulse yalnız gelecekte gerekebilir diye kurmak
- OpenAPI generator yalnız gelecekte API olacak diye eklemek

M0'ın başarısı çok kod yazması değil; sonraki bütün milestone'ların **aynı standartlar, gerçek PostgreSQL ve enforce edilebilir kalite kapıları** üzerinde ilerlemesini sağlamasıdır.

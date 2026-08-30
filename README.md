# MarsOtomasyon

MarsOtomasyon; şirket içi kullanım odaklı, Türkçe, hızlı ve sade bir **ön muhasebe + operasyon** uygulamasıdır. Tek deployable Laravel modular-monolith olarak geliştirilir; transactional business authority PostgreSQL'dedir.

## Güncel durum

- Resmî V1 roadmap: **M0–M24**.
- Commercial Functional Gate: **M0–M13**.
- Kod/plan/PR reconciliation: [`plan/21_MILESTONE_DURUM_MATRISI.md`](plan/21_MILESTONE_DURUM_MATRISI.md).
- Commercial Functional Gate **M0–M13 tamamlandı**; sonraki resmî açık ana domain milestone'u **M17 — E-Ticaret Integration Core + WooCommerce**.
- M17/M18/M19/M20/M21/M23 için bazı erken foundation capability'leri vardır; bunlar ilgili milestone'un tamamlandığı anlamına gelmez.
- Production release gate M23 + M24 tamamlanmadan V1 production-ready sayılmaz.

> Tarihsel PR başlığındaki `Mxx` etiketi resmî V4.2 milestone numarasıyla çakışabilir. Güncel durum için her zaman `plan/16_UYGULAMA_SIRASI_MILESTONE.md` + `plan/21_MILESTONE_DURUM_MATRISI.md` birlikte kullanılır.

## Stack

- PHP **8.5**
- Laravel **13**
- PostgreSQL **18** — production/CI transactional database
- Valkey — cache, queue, rate-limit, lock ve geçici koordinasyon
- Blade + Alpine / Vite
- Node **24** / npm **11**
- Pest 5 + Laravel plugin + Browser/Playwright
- Pint
- Larastan / PHPStan

## Local kurulum

Ön koşullar: PHP 8.5, Composer, PostgreSQL 18, Valkey, Node 24 ve npm 11.

```bash
composer install
npm ci
cp .env.example .env
php artisan key:generate
```

`.env` içinde en az PostgreSQL ve Valkey bağlantılarını yerel ortama göre ayarla. Varsayılan örnek:

- PostgreSQL: `127.0.0.1:5432`, DB `marsotomasyon`
- Valkey/Redis protocol: `127.0.0.1:6379`
- locale: `tr`
- timezone: `Europe/Istanbul`

Ardından:

```bash
php artisan migrate
npm run build
php artisan serve
```

Queue kullanan async akışlar için ayrı process'te Laravel queue worker çalıştırılır.

## Kalite ve test komutları

```bash
composer format:check
composer analyse
composer test
composer test:unit
composer test:integration
composer test:architecture
composer test:browser
npm run build
```

`composer ci`; format check + static analysis + non-browser test zincirini çalıştırır.

Business correctness SQLite/MySQL davranışına göre kabul edilmez. PostgreSQL constraint, transaction, locking ve integration testleri release authority'sinin parçasıdır.

## CI

Required Foundation job isimleri sabittir:

- `quality`
- `postgres-tests`
- `browser-smoke`
- `security`

Self-hosted runner benchmark workflow'u **manuel (`workflow_dispatch`)** tutulur. Required Foundation işleri, self-hosted makinede gerekli toolchain/servisler güvenli ve ölçülmüş biçimde hazır olmadan local runner'a taşınmaz.

`main` branch protection/required-check enforcement repo operasyon blocker'ı olarak ayrıca izlenir; yalnız CI'ın yeşil olması branch protection uygulanmış olduğu anlamına gelmez.

## Mimari kurallar

- Kesinleşmiş finans/stok/ticari kayıtlar keyfi UPDATE/DELETE edilmez; reversal/adjustment/return/cancel ile düzeltilir.
- Para/miktar/kur için PHP binary float source-of-truth değildir.
- `stock_movements`, `account_transactions` ve `treasury_movements` kendi domainlerinde immutable ledger authority'dir.
- External HTTP çağrıları DB transaction içinde yapılmaz; gerekli yan etkiler outbox/queue üzerinden commit sonrasına taşınır.
- Tenant sınırı `company_id` ile korunur; kritik cross-company referanslar fail-closed çalışır.
- Production direct SQL/tinker ile business edit yapılmaz.

Ayrıntılı karar otoritesi: [`plan/00_KARAR_KAYDI.md`](plan/00_KARAR_KAYDI.md).

## Geliştirme sırası

Aktif V1 sırası:

`M11 Çek/Senet → M14 Üretim → M15 Fason → M16 İthalat/Konteyner → M17 E-Ticaret Core/WooCommerce → M18 Marketplace Adapter Pack → M19 B2B → M20 Communication/API → M21 Product Image Operations → M22 Installation PDF Builder → M23 Production Candidate Hardening → M24 Migration/Go-Live`

Her milestone `entry gate → schema → domain action → transaction/invariant → authorization → UI → tests → PostgreSQL CI → audit/observability` sırasıyla kapanır.

## Plan ve kabul referansları

- **Master Plan V4.2:** [`plan/README.md`](plan/README.md)
- **Milestone sırası:** [`plan/16_UYGULAMA_SIRASI_MILESTONE.md`](plan/16_UYGULAMA_SIRASI_MILESTONE.md)
- **Milestone durum matrisi:** [`plan/21_MILESTONE_DURUM_MATRISI.md`](plan/21_MILESTONE_DURUM_MATRISI.md)
- **Kilitli kararlar:** [`plan/00_KARAR_KAYDI.md`](plan/00_KARAR_KAYDI.md)
- **Açık karar / entry gate:** [`plan/19_ACIK_KARARLAR.md`](plan/19_ACIK_KARARLAR.md)
- **İş kuralları / invariantlar:** [`plan/06_IS_KURALLARI_VE_INVARIANTLAR.md`](plan/06_IS_KURALLARI_VE_INVARIANTLAR.md)
- **Test / CI:** [`plan/14_TEST_CI_KALITE.md`](plan/14_TEST_CI_KALITE.md)
- **Definition of Done:** [`plan/18_DEFINITION_OF_DONE.md`](plan/18_DEFINITION_OF_DONE.md)
- **UI / Akış kabul referansı:** [`plan/26_V16_3_TASARIM_UYUMU.md`](plan/26_V16_3_TASARIM_UYUMU.md)
- **Gelecek genişleme altyapısı:** [`plan/27_GELECEK_GENISLEME_ALTYAPISI.md`](plan/27_GELECEK_GENISLEME_ALTYAPISI.md)
- **Planlı post-V1 genişlemeler:** [`plan/28_PLANLI_GENISLEMELER.md`](plan/28_PLANLI_GENISLEMELER.md)

Güncel kullanıcı-visible tasarım baseline'ı **MarsOtomasyon V16.3 — Genel Tasarım Temizliği**'dir.

`MarsEski` yeni uygulamanın kod tabanı değildir; yalnız V16.3 ile uyumlu business correctness, edge-case, test ve migration referansıdır.
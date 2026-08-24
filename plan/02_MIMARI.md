# 02 — Mimari

## Stil
**Laravel-native modular monolith.** Tek deployable uygulama, net domain sınırları. Microservice yok; gerektiğinde ileride ayrılabilecek modül sınırları korunur.

## Runtime
- PHP 8.5
- Laravel 13
- PostgreSQL 18
- Valkey
- Laravel Queue workers
- Laravel Scheduler
- OpenLiteSpeed/Nginx gibi web katmanı deploy ortamına göre seçilebilir; domain kodu web sunucusuna bağlı değildir

## Katmanlar
Her domain modülünde mümkün olduğunca:
- HTTP/UI adapter
- Application use-case/service
- Domain model/rules
- Persistence
- Events/outbox
- Tests

Gereksiz repository/interface kalabalığı kurulmaz. Eloquent domain doğruluğunu bozmadığı yerde doğrudan kullanılabilir.

## Modül sınırları
- Core/Identity/Settings
- Accounts
- Catalog/Inventory
- Sales
- Purchasing
- Treasury
- Instruments (çek/senet)
- Production/Subcontracting
- Returns
- Import
- Commerce/B2B
- Communication
- Reporting
- Files/Printing
- Operations/Migration

Modüller birbirinin tablolarını keyfi mutate etmez. Cross-domain değişimler application service + transaction/event ile yapılır.

## Transaction sınırı
Kullanıcı açısından tek business action olan değişimler tek DB transaction'dır. Örnek satış faturası posting:
1. belgeyi kilitle/doğrula
2. invoice state
3. sipariş progress
4. stock movement
5. account transaction
6. outbox
7. commit

Yan etkiler (SMS/e-posta/webhook) commit sonrası outbox/queue ile gönderilir.

## Idempotency
Posting, webhook, marketplace import ve external retry yollarında idempotency zorunludur. Aynı external event veya business command ikinci kez finans/stok etkisi üretemez.

## Concurrency
- kritik satırlarda row lock / optimistic version uygun yerde kullanılır
- stok, sipariş miktarı ve çek/senet settlement yarış koşulları test edilir
- benzersiz business key'ler DB constraint ile korunur

## Arama
V1: PostgreSQL FTS + `pg_trgm`. Ürün aramada kod/barkod/QR/ad; cari aramada kod/unvan/vergi/telefon/e-posta gibi gerçek alanlar.

## Cache
Valkey yalnız yeniden üretilebilir veri, rate limit, distributed lock, queue/session gibi amaçlarda kullanılır. Finans ve stok authority cache değildir.

## Dosyalar
Metadata PostgreSQL'de; binary dosyalar storage abstraction üzerinden local/S3-compatible hedefe konabilir. DB transaction ile dosya lifecycle arasında orphan cleanup stratejisi gerekir.

## Observability
Structured log + request/correlation id + queue/job context + audit trail. Finans/stok business audit'i application log'dan ayrı tutulur.

## Deployment
İlk hedef tek sunucu/az kullanıcı için sade kurulumdur. Horizontal scale için gereksiz altyapı önceden eklenmez.

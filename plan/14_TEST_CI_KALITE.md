# 14 — Test, CI ve Kalite

## CI zorunlu kapılar
- dependency install doğrulaması
- formatter/lint
- static analysis
- unit tests
- feature/integration tests
- gerçek PostgreSQL 18 service ile migration/test
- gerektiğinde Valkey service
- security/dependency advisory

SQLite ile geçen test PostgreSQL davranışının yerine kabul edilmez.

## Test piramidi
### Unit
Saf hesaplama, state transition ve değer nesneleri.

### Feature/Integration
Asıl ağırlık burada: HTTP/use-case + PostgreSQL transaction + authorization + ledger etkisi.

### Browser/E2E
Kritik V16.3 akışları: login, cari, ürün arama, satış siparişi, fatura posting, tahsilat, mal kabul, ödeme, workspace dirty state gibi yüksek değerli senaryolar.

## Zorunlu invariant testleri
- kısmi faturalama ve kalan miktar
- kısmi sevk
- aynı invoice ikinci kez post edilemez
- aynı webhook ikinci sipariş yaratamaz
- transfer toplam stok değerini bozmaz
- goods receipt ikinci stock IN yaratmaz
- tahsilat/ödeme cari + treasury etkisi atomik
- çek/senet settlement concurrency
- reversal ledger geçmişini korur
- permission bypass engellenir

## Concurrency
En az stock/order progress, sequence, instrument settlement ve idempotency için gerçek yarış koşulu testleri bulunur.

## UI acceptance
V16.3 ekranı için:
- runtime error yok
- dead button yok
- stale legacy renderer yok
- duplicate kullanıcı-visible alan/sekme yok
- gerçek domain verisi kullanılıyor
- route/action gerçek

## Test verisi
Factory/fixture'lar gerçek business constraint'lere uyar; aşırı sihirli global seed test bağımlılığı yaratmaz.

## Coverage
Yüzde hedefi tek kalite ölçütü değildir. Finans/stok/posting/state/authorization kodunda branch ve failure path testleri zorunludur.

## Hata senaryoları
Sadece happy path değil: transaction rollback, provider timeout, duplicate request, stale version, invalid transition, yetkisiz erişim, malformed import, partial external failure.

## Merge kuralı
Kırmızı CI ile main'e değişiklik tamamlanmış sayılmaz. Flaky test disable edilmek yerine kök neden düzeltilir.

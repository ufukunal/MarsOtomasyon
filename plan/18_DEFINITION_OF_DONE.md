# 18 — Definition of Done

Bir iş yalnız kod yazıldığı için tamamlanmış sayılmaz.

## Modül/feature DoD
- schema/migration hazır
- domain rule/use-case hazır
- validation ve authorization hazır
- transaction sınırı doğru
- idempotency/concurrency ihtiyacı ele alınmış
- V16.3 kullanıcı-visible davranışına uyuyor
- dead button/placeholder yok
- audit/observability gereken yerde var
- unit/feature/integration testleri var
- PostgreSQL CI green
- hata/failure path test edilmiş
- dokümantasyon/plan gerekiyorsa güncel

## Finans/Stok ekstra DoD
- ledger etkisi açıkça tanımlı
- reversal/correction yolu var
- duplicate posting engelli
- transaction rollback testi var
- concurrency kritikse yarış testi var

## Entegrasyon ekstra DoD
- credential güvenliği
- idempotency
- retry/backoff
- rate limit
- external mapping
- hata merkezi/görünürlük
- malformed/duplicate payload testleri

## UI ekstra DoD
- gerçek backend verisi
- gerçek route/action
- loading/empty/error state
- readonly/edit ayrımı
- keyboard/focus temel davranışı
- kullanıcı-visible teknik jargon yok
- runtime console/backend error yok

## Import/Export ekstra DoD
- dry-run/preview veya açık validasyon
- satır bazlı hata raporu
- duplicate policy
- authorization
- büyük dosyada memory/time sınırı
- audit

## Backup DoD
Backup üretmek yetmez; restore drill başarılı değilse backup özelliği tamamlanmış değildir.

## Milestone DoD
Milestone içindeki zorunlu dikey akış en baştan sona gerçek veritabanı üzerinde çalışmalı ve smoke/E2E testi geçmelidir.

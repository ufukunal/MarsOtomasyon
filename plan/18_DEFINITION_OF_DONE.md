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
Bir provider/marketplace adapterı ancak aşağıdakiler tamamlandığında hazır sayılır:
- credential schema tanımlı
- secret encrypted-at-rest ve save sonrası masked read-back
- connection test gerçek provider contract'a göre çalışıyor
- capability matrix tanımlı
- unsupported/manual operasyonlar açıkça ayrılmış
- external entity mapping var
- provider-account scope var
- inbound idempotency/dedupe var
- Outbox/retry/backoff var
- provider rate-limit policy var
- ambiguous timeout/result reconcile davranışı var
- polling cursor/watermark restart-safe
- webhook varsa signature/replay/dedupe korunuyor
- stock publish CURRENT_DESIRED_STATE stale-retry guard kullanıyor
- malformed/duplicate payload testleri var
- normalized problem/error center kaydı var
- provider fixture/contract testleri var
- API version/deprecation referansı kayıtlı
- production secret CI/log/audit payload'a sızmıyor

Marketplace adapter seti:
- WooCommerce
- Trendyol
- Hepsiburada
- Amazon SP-API
- n11
- PttAVM
- idefix
- Çiçeksepeti

Bir adapter yalnız ürün bağlayabildiği için “tam entegre” sayılmaz. Hangi capability'lerin gerçekten çalıştığı kanal kartında/diagnostics'te görülebilir olmalıdır.

## Marketplace operasyon DoD
Provider destekliyorsa ilgili capability için ayrıca:
- ürün/kategori/özellik mapping
- listing create/update/status
- stok güncelleme
- fiyat güncelleme
- sipariş alma
- sevkiyat/kargo güncelleme
- iptal
- iade/talep
- fatura referansı/link/dosya
- ürün/sipariş soruları
- settlement/muhasebe evidence
uçtan uca test edilir.

Provider desteklemiyorsa aynı aksiyon UI'da sessiz no-op olmaz; disabled/unsupported/manual davranış gösterir.

## Amazon ekstra DoD
- marketplace/region scope açık
- SP-API/LWA authorization lifecycle güvenli
- listing/catalog mapping testli
- Orders/shipment normalization testli
- FBA/FBM capability farkı tanımlı
- report/settlement import idempotent

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

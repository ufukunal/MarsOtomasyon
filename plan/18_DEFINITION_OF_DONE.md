# 18 — Definition of Done V4.1

Bir iş yalnız kod yazıldığı için tamamlanmış sayılmaz.

## M0 / Foundation DoD
M0 ancak:
- fresh clone install çalışıyor
- PostgreSQL 18 migrate/test çalışıyor
- Valkey smoke çalışıyor
- formatter + static analysis command var
- GitHub Actions CI workflow çalışıyor
- dependency advisory/security check var
- browser/E2E route-smoke skeleton var
- required status-check isimleri tanımlı
- main protection uygulanabiliyorsa aktif; uygulanamıyorsa açık repo blocker'ı kayıtlı
- V16.3 design/reference artifact veya immutable source/hash referansı versionlanmış
olduğunda tamamdır.

## Modül/feature DoD
- ilgili `19_ACIK_KARARLAR.md` entry gate kapanmış veya capability açıkça scope dışı
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
- authoritative ledger açık: AccountTransaction / StockMovement / TreasuryMovement
- source-effect identity tanımlı
- reversal/correction yolu var
- duplicate posting engelli
- transaction rollback testi var
- concurrency kritikse yarış testi var
- posting-period testli
- currency/decimal/rounding snapshot doğru

### Stok ekstra
- negative stock BLOCK
- reservation available-stock cap
- moving-average cost testli
- transfer in-transit quantity/value reconcile
- positive count zero-cost yaratmıyor

### Satış ekstra
- dispatch stock OUT authority
- direct invoice istisnası testli
- reversal-safe remaining/progress
- over-cancel/over-return dahil quantity caps

### Mal Kabul ekstra
- accepted/pending/rejected split reconcile
- pending/rejected unavailable
- reclassification second stock IN üretmiyor

## Vergi / fiyat hesap DoD
- product price net authority
- KDV dahil/hariç input normalization
- line/document discount sırası
- tax base + tax rounding deterministic
- tax zero reason
- posted snapshot immutable

## Treasury/POS DoD
- cash/bank/POS balance TreasuryMovement'tan rebuildable
- source records direct balance mutate etmiyor
- POS gross cari effect + commission separation
- Pending/Settled/Reversed/Chargeback flow
- bank settlement second cari effect üretmiyor

## Çek/Senet ekstra DoD
- received/issued delivery-time cari effect
- bank settlement second cari effect üretmiyor
- ciro supplier payable effect exactly-once
- dishonored endorsed reversal chain
- holder/physical location/history

## Entegrasyon ekstra DoD
Bir provider/marketplace adapterı ancak aşağıdakiler tamamlandığında hazır sayılır:
- provider registry entry: docs/version/auth/verified-date/capability
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
- product media/image capability deterministic
- malformed/duplicate payload testleri var
- normalized problem/error center kaydı var
- provider fixture/contract testleri var
- API version/deprecation referansı kayıtlı
- production secret CI/log/audit payload'a sızmıyor

**V1 doğrulanmış marketplace adapter seti:**
- WooCommerce
- Trendyol
- Hepsiburada
- Amazon SP-API
- n11
- PttAVM
- idefix
- Allesgo

Bir adapter yalnız ürün bağlayabildiği için “tam entegre” sayılmaz. Hangi capability'lerin gerçekten çalıştığı kanal kartında/diagnostics'te görülebilir olmalıdır.

## Marketplace finans DoD
Provider settlement/payout evidence capability varsa:
- legal customer snapshot ile financial clearing Account ayrılmış
- invoice clearing receivable effect testli
- payout clearing + bank TreasuryMovement atomik/idempotent
- commission/service/shipping/chargeback ayrı effect
- duplicate settlement row ikinci effect üretmiyor
- clearing reconciliation yapılabiliyor
- actual fee/contribution yalnız evidence'dan geliyor

durumları tamamlanmadan finance capability “hazır” sayılmaz.

## Marketplace operasyon DoD
Provider destekliyorsa ilgili capability için ayrıca:
- ürün/kategori/özellik mapping
- listing create/update/status
- media/main/gallery publish
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

## Deferred marketplace kabul kuralı
Çiçeksepeti, Pazarama, Koçtaş, Teknosa, Temu Türkiye ve Boyner V1 DoD kapsamı değildir.

Bu kanallardan biri ancak:
- güncel resmî API dokümanı veya gerçek seller/partner erişimi doğrulanmış
- authentication ve endpoint contract'ları net
- capability matrix çıkarılmış
- rate-limit/pagination/status davranışı belgelenmiş
- fixture/contract testleri hazırlanabilir
olduğunda aktif adapter setine alınır.

## B2B DoD
- B2BUser internal User değildir
- separate auth guard/context
- activation/deactivation
- login/logout/password reset
- session/token revoke
- brute-force/rate-limit
- pre-bound Account değiştirilemiyor
- internal admin permission sızıntısı yok
- server-side B2B permission
- risk/exposure policy
- B2B discount = Cari İskontosu

## UI ekstra DoD
- gerçek backend verisi
- gerçek route/action
- loading/empty/error state
- readonly/edit ayrımı
- keyboard/focus temel davranışı
- kullanıcı-visible teknik jargon yok
- runtime console/backend error yok
- provider capability yoksa sessiz no-op yok

## Rapor DoD
Final V1 katalog `13_UI_UX_RAPORLAMA.md` içindeki **40 stabil report_key** ile ölçülür.

Her implemented report:
- gerçek route
- meaningful source query/read model
- permission/company scope
- filters
- expected totals fixture
- export/print where applicable
- scheduled runtime authorization where applicable
ile test edilir.

M13 yalnız tamamlanan commercial domain raporlarını gerektirir; final 40 M23 production-candidate gate'inde tamamlanır.

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
Milestone içindeki zorunlu dikey akış en baştan sona gerçek PostgreSQL üzerinde çalışmalı ve smoke/E2E testi geçmelidir.

## Production Candidate DoD
M13 commercial functional gate production-ready değildir.

Production candidate yalnız M23 sonunda:
- full Gate A/B/C green
- security/company/B2B auth review
- backup + isolated restore drill
- Recovery Mode/recovery barrier
- ledger/reconciliation checks
- active provider compatibility review
- final report catalog
- CI/main protection doğrulaması
başarılıysa verilir.

# MarsOtomasyon — Master Plan V4.2 — V16.3 Tasarım Uyumlu

Bu klasör `ufukunal/MarsOtomasyon` için **otoriter geliştirme planıdır**.

`ufukunal/MarsEski` application code yeni projeye taşınmaz. Eski repo yalnız:
- business rule / invariant,
- edge-case,
- test senaryosu,
- migration/veri eşleme,
- operasyon/güvenlik dersleri
kaynağıdır.

V4.2, V4.1 code-ready doğruluk/gate yapısını korur; ileride özellik eklerken çekirdeği bozmayacak **future extension seam** sözleşmelerini `27_GELECEK_GENISLEME_ALTYAPISI.md` ile, resmî post-V1 genişleme roadmap'ini ise `28_PLANLI_GENISLEMELER.md` ile tanımlar.

## Durum
- Plan: **V4.2 — code-ready + future-ready, V16.3 tasarım uyumlu**
- UI referansı: **MarsOtomasyon V16.3 — Genel Tasarım Temizliği**
- Future extension contract: `27_GELECEK_GENISLEME_ALTYAPISI.md`
- Planned extension roadmap: `28_PLANLI_GENISLEMELER.md`
- PHP 8.5 + Laravel 13
- PostgreSQL 18 only
- PostgreSQL FTS + `pg_trgm`
- Valkey
- Laravel-native modular monolith
- İlk deployment: tek sunucu / az kullanıcı
- Git ana branch: `main`
- Uygulama repo: `ufukunal/MarsOtomasyon`

## Ürün karakteri
MarsOtomasyon öncelikle **Türkçe, hızlı ve sade bir ön muhasebe/operasyon uygulamasıdır**. Kullanıcıya ERP mühendisliği, queue/provider/internal-state jargonları veya generic enterprise platform ekranları gösterilmez.

Ana navigasyon:
`Ana Sayfa → Cariler → Ürün/Stok → Satış → Alış → Üretim → Kasa/Banka → Çek/Senet → İadeler → İthalat → E-Ticaret/B2B → İletişim → Raporlar → Ayarlar`.

## Plan otoritesi
Çelişki halinde sıra:
1. `00_KARAR_KAYDI.md` locked decisions.
2. İlgili V1 business-owner plan belgesi (`03`–`25`).
3. M25–M31 planlı genişlemelerde `28_PLANLI_GENISLEMELER.md` ilgili feature owner/scope sözleşmesidir.
4. `26_V16_3_TASARIM_UYUMU.md` kullanıcı-visible V1 ekran/akış sözleşmesi; post-V1 UI değişikliği yeni onaylı UI contract ister.
5. `14_TEST_CI_KALITE.md` + `18_DEFINITION_OF_DONE.md` acceptance gates.
6. `27_GELECEK_GENISLEME_ALTYAPISI.md` genişleme **yöntemi ve seam** authority'sidir; V1 veya `28` feature scope'unu override etmez.
7. Migration/application davranışı.
8. `MarsEski` code/eski belge.

`27` içindeki aday listelerinden `28` içine terfi etmiş özelliklerde **`28` resmî roadmap statüsüdür**.

**Not:** V16.3 kullanıcı-visible tasarımına aykırı davranış yalnız eski repoda vardı diye geri alınmaz.

## Locked ana ürün kararları
- Cari: `account_transactions` bakiye ledger; OpenItem/fatura-allocation yok.
- Cari V1: tek book currency; ham farklı para birimleri tek bakiyede toplanmaz.
- Stok: `stock_movements` authority; aynı fiziksel olay exactly-once.
- Negatif stok: V1'de BLOCK.
- Satış physical stock OUT: sevkiyat/irsaliye authority; irsaliyesiz direct invoice kendi OUT effect'ini üretir.
- Stok maliyeti: moving weighted average.
- Kısmi sevk/faturalama/mal kabul first-class.
- Core lot/seri yok.
- Ürün başına tek satış + tek alış fiyatı.
- B2B fiyatı = **ürün satış fiyatı − Cari İskontosu**; ayrı price-list truth yok.
- Marketplace müşteri snapshot'ı ile finansal clearing counterparty ayrıdır.
- Treasury bakiye authority `treasury_movements` ledger'ıdır.
- Basit üretim: `Reçete → Emir → Malzeme Çıkışı → Mamul Girişi → Tamamla`.
- Generic QMS/MRP/OEE/ECO/Shop Floor platformu yok.
- Hazır Rapor Merkezi; generic report/document designer yok.
- Canlı banka API/open-banking V1'de yok.
- PostgreSQL search; ayrı search daemon yok.

## V4.1 code-ready düzeltmeleri
- Negatif stok ve reservation/oversell policy kilitlendi.
- Dispatch/invoice stock authority source-effect matrix kilitlendi.
- Moving-average costing M4 öncesi karar olarak kilitlendi.
- KDV dahil/hariç input + net/tax/discount/rounding contract tanımlandı.
- Cari book currency sınırı tanımlandı.
- Marketplace clearing/payout/fee modeli tanımlandı.
- Treasury movement authority netleştirildi.
- Transfer/fason in-transit custody ve carrying value ilkesi tanımlandı.
- B2B external auth sınırı internal kullanıcıdan ayrıldı.
- Milestone entry-gate'leri `19_ACIK_KARARLAR.md` ile bağlandı.
- M0 gerçek CI/toolchain/branch-protection çıkış kriteriyle güçlendirildi.
- M12/M13 sequencing ve go-live channel cutover uyumsuzlukları düzeltildi.

## V4.2 future-ready altyapı
Geleceğe hazırlıkta kural **spekülatif framework değil, dar extension seam** bırakmaktır.

Hazırlanan başlıca seam'ler:
- provider family registry: marketplace, kargo, ödeme, e-belge, iletişim, kur, storage, OCR/AI, dış muhasebe, feed/discovery
- typed capability contract
- versioned internal event catalog convention
- stable external identity/source-effect contract
- code/config based feature registry
- import parser registry
- report registry
- Product SKU'yu bozmadan opsiyonel ürün ailesi/varyant grouping yolu
- mobil depo/scanner API yolu
- shipping/payment/accounting/feed adapter aileleri
- Attachment → extraction/review → domain action OCR yolu
- AI recommendation seam; hiçbir AI sonucu ledger authority değildir
- satış sonrası servis/garanti, hafif CRM, reorder/forecast ve BI'nin ledger'dan ayrıldığı sınırlar

Ayrıntı: `27_GELECEK_GENISLEME_ALTYAPISI.md`.

## Resmî planlı post-V1 genişlemeler
M24 sonrası roadmap:

`M25 Product Family/Variant → M26 Barkod/Termal Etiket → M27 Mobil Depo/Scanner → M28 Kargo API Adapterları → M29 OCR Fatura/Dekont → M30 Hafif CRM → M31 BI Export`

Bu modüller V1 M24 go-live'ını bloklamaz. Her biri FeatureKey + additive migration + mevcut authority ledger sınırlarıyla açılır.

Ayrıntı: `28_PLANLI_GENISLEMELER.md`.

## V4'te eski plandan geri alınan kritik ayrıntılar
V16.3 ile uyumlu olduğu için korunan başlıca kurallar:
- `NUMERIC(20,6)` money/qty/cost ve `NUMERIC(20,10)` kur standardı.
- Company isolation + CrossCompanyLeak testleri.
- Posted kayıt immutability + controlled reversal/correction.
- Explicit quantity formulas ve over-dispatch/over-invoice/over-receipt blokajı.
- Depo transferinde `çıkış → yolda → kısmi/tam kabul` ve reconciliation.
- Çek/senet delivery-time cari effect ve later settlement'ta double-effect yasağı.
- POS gross cari effect / komisyon-net settlement ayrımı.
- E-Belge lifecycle'ın internal faturadan ayrılması.
- Provider-account external identity + inbound message dedupe.
- `IMMUTABLE_EVENT_SNAPSHOT` / `CURRENT_DESIRED_STATE` Outbox semantiği.
- Ambiguous provider response için query/reconcile; blind resend yok.
- Backup manifest/checksum/release/schema + restore drill + Recovery Mode.
- Stable legacy `source_instance_id`, historical side-effect suppression ve cutover reconciliation.
- Purchase/FX/late-landed-cost double-count korumaları.
- Concurrent-write-safe schema backfill.
- Gate A/B/C test yaklaşımı ve granular vertical milestones.

## UI uygulama kuralı
- Yeni belge ilgili listeden `Yeni` ile açılır.
- Detail readonly; edit ayrı route.
- Finalized belge input görünümünde düzenlenmez.
- Generic placeholder yok.
- Her görünür action gerçek route/modal/drawer/action çalıştırır veya açıkça disabled+reason gösterir.
- Teknik internal state normal kullanıcıya çıkmaz.

## Delivery stratejisi
`16_UYGULAMA_SIRASI_MILESTONE.md` küçük dikey dilimler kullanır.

Her teslim:
`entry gate → schema → use-case → transaction/invariant → authorization → approved UI → tests → PostgreSQL CI → audit/observability`.

Büyük modüller tek dev committe yazılmaz; independently test edilebilir vertical slice'lar atomic commitlerle ilerler.

Gelecek feature için ayrıca:
`need → owner → extension seam → additive schema → feature gate → vertical slice → tests → rollout`.

## Başlangıç okuma sırası
Yeni geliştirmeye başlarken:
1. `00_KARAR_KAYDI.md`
2. `16_UYGULAMA_SIRASI_MILESTONE.md`
3. `19_ACIK_KARARLAR.md` ilgili milestone entry gate'i
4. yapılacak modülün owner belgesi
5. `06_IS_KURALLARI_VE_INVARIANTLAR.md`
6. `14_TEST_CI_KALITE.md`
7. V1 kullanıcı yüzeyi için `26_V16_3_TASARIM_UYUMU.md`
8. genişleme yöntemi için `27_GELECEK_GENISLEME_ALTYAPISI.md`
9. M25–M31 için ayrıca `28_PLANLI_GENISLEMELER.md`

Bu kaynaklar birlikte uygulanmadan modül tamamlanmış sayılmaz.

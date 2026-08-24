# 00 — Karar Kaydı V4.2 — V16.3 Tasarım Uyumlu

Bu belge MarsOtomasyon için kilitli mimari ve ürün kararlarını tutar. Çelişki halinde bu belge, ilgili business-owner planı ve `26_V16_3_TASARIM_UYUMU.md` birlikte otoritedir.

## A. LOCKED DECISIONS

### K-001 — Ürün tipi ve deployment hedefi
MarsOtomasyon şirket içi kullanım odaklı, Türkçe, hızlı ve sade bir **ön muhasebe + operasyon** uygulamasıdır. İlk hedef SaaS değildir. Production başlangıcı tek sunucu/az kullanıcıdır; Kubernetes, multi-region ve hyperscale mimarisi kurulmaz.

### K-002 — Temiz yeniden yazım
- Yeni uygulama `ufukunal/MarsOtomasyon` reposunda temiz kurulacaktır.
- `MarsEski` application code kopyalanmaz; yalnız iş kuralı, edge-case, test senaryosu ve migration referansıdır.

### K-003 — Uygulama stack'i
- PHP 8.5.
- Laravel 13.
- PostgreSQL 18.
- Valkey: cache, queue, rate-limit, lock ve geçici koordinasyon.
- Blade + Alpine temel UI; Livewire uygun yerlerde; özel grid/görsel düzenleyici gibi yüzeylerde dedicated JS.
- Composer Laravel'in doğal bağımlılık yönetimidir.

### K-004 — PostgreSQL tek transactional DB
- Production ve CI transactional DB yalnız PostgreSQL 18'dir.
- MySQL/MariaDB/SQLite davranışına göre business schema tasarlanmaz.
- Business correctness explicit transaction, locking ve DB constraint ile korunur.

### K-005 — Arama altyapısı
İlk sürüm PostgreSQL Full-Text Search + `pg_trgm` kullanır. Ayrı Meilisearch/Typesense/Elasticsearch/OpenSearch/Sonic servisi ancak ölçülmüş ihtiyaç sonrası yeni karar ile eklenebilir.

### K-006 — Laravel-native modular monolith
Tek deployable Laravel backend. Modüller domain/capability sınırıdır; mikroservis değildir. Gereksiz repository/interface/framework katmanı kurulmaz.

### K-007 — Commit sonrası entegrasyon
External HTTP çağrısı DB transaction içinde çalışmaz. Durable async yan etkiler gerekiyorsa PostgreSQL transactional outbox + Laravel queue worker kullanılır.

### K-008 — Git
Ana geliştirme branch'i `main`dir. Atomic/test edilmiş commit esastır; kırmızı CI tamamlanmış iş sayılmaz.

### K-009 — Para, miktar ve kur doğruluğu
- PHP binary `float` finansal source-of-truth değildir.
- Money/qty/cost varsayılanı `NUMERIC(20,6)`.
- Kur varsayılanı `NUMERIC(20,10)`.
- Currency minor-unit ve rounding policy explicit uygulanır.

### K-010 — Kesinleşmiş kayıt değişmezliği
Taslak düzenlenebilir. Kesinleşmiş ticari/finansal/stok kayıtları keyfi UPDATE/DELETE edilmez; düzeltme reversal/adjustment/return/iptal hareketiyle yapılır.

### K-011 — Cari sistemi bakiye bazlıdır
Cari authority `account_transactions` hareket defteridir.
- debit `+`, credit `-`.
- satış faturası müşteri bakiyesini artırır; tahsilat azaltır.
- alış faturası tedarikçi borcunu artırır; ödeme azaltır.
- fatura bazlı OpenItem/allocation/settlement core değildir.
- vade bilgi ve raporlama alanıdır.
- fazla tahsilat/ödeme signed bakiyede görünür.

### K-012 — Kısmi işlemler first-class
Kısmi sevk, faturalama, mal kabul, iade, üretim ve fason normal senaryodur. Kalan miktarlar negatif olamaz ve over-operation varsayılan olarak bloklanır.

### K-013 — Stok authority
`stock_movements` fiziksel stok source-of-truth'tur. Reservation hareket değildir. Aynı fiziksel olay iki kez stok etkisi üretemez.

### K-014 — Company isolation ve Branch ayrımı
- Tenant-scoped kritik kayıtta `company_id` zorunludur; referenced tenant entity aynı company olmalıdır.
- Company hukuki/tenant sınırı; Branch operasyon birimidir.
- Her tabloya mekanik `branch_id` eklenmez; yalnız business anlamı varsa kullanılır.

### K-015 — UI tasarım otoritesi
Onaylı kullanıcı-visible baseline **MarsOtomasyon V16.3 — Genel Tasarım Temizliği**'dir.
- kullanıcı dili Türkçe ve domain odaklıdır,
- generic placeholder form/detail yoktur,
- yeni belge listeden açılır,
- detail readonly, edit ayrı route'tur,
- kesinleşmiş belge readonly'dir,
- teknik queue/outbox/provider/idempotency jargonları normal kullanıcıya gösterilmez,
- görünür dead button yoktur.

### K-016 — Ana navigasyon
`Ana Sayfa → Cariler → Ürün/Stok → Satış → Alış → Üretim → Kasa/Banka → Çek/Senet → İadeler → İthalat → E-Ticaret/B2B → İletişim → Raporlar → Ayarlar`.

Kasa/Banka ana menüsü kısa tutulur:
`Tahsilat, Ödeme, Gider, Kasa Hareketleri, Banka Hareketleri, Virman, Ekstre İçe Aktar`.

### K-017 — Ürün fiyatı
Core ürün modelinde tek satış fiyatı + tek alış fiyatı vardır. Çoklu fiyat listesi yoktur. B2B ayrı fiyat listesi kullanmaz; **Cari İskontosu** kullanılır.

### K-018 — Lot/seri kapsamı
Core V1 ürün/stok tasarımında lot/seri takibi yoktur. Gerçek ihtiyaç çıkarsa yeni scope/karar gerekir.

### K-019 — Basit üretim
`Reçete → Üretim Emri → Malzeme Çıkışı → Mamul Girişi → Tamamla`.
Routing, Work Center, ECO, OEE, APS ve generic shop-floor engineering core değildir.

### K-020 — Fason
Fason ayrı stok motoru kurmaz. `Gönderilen Malzeme → Gelen Mamul → Fire/Eksik → Kalan → Tamamla` aynı stok authority'sini kullanır.

### K-021 — Mal kabul kalite yaklaşımı
Core mal kabul miktar karşılaştırması + `Uygun / Kontrol Bekliyor / Uygun Değil` kararıdır. Generic QMS/QCP/CAPA/8D/SPC platformu core değildir.

### K-022 — Raporlama
Hazır **Rapor Merkezi** vardır; hedef 40 hazır rapor / 8 kategoridir. Generic raw SQL/low-code report/document designer yoktur. Belge PDF'leri server-owned versioned template ile üretilir. Ürün Kurulum PDF Builder yalnız kendi domain'ine özel araçtır.

### K-023 — Banka kapsamı
Banka hesabı/hareketi, manuel hareket, Excel/CSV/MT940 ekstre import ve mutabakat vardır. Canlı banka API/open-banking V1 kapsamında yoktur.

### K-024 — Kasa sayımı
Kasa sayımı ürün/cari/KDV belgesi değildir. Kasa + tarih + sayımı yapan + kupür/adet + sistem bakiyesi + sayılan toplam + fark + açıklama ile çalışır; tamamlama exactly-once adjustment üretir.

### K-025 — Tahsilat / ödeme türleri
Kullanıcı-visible tipler: `Nakit, Banka Havale/EFT, POS, Sanal POS, Çek, Senet, Diğer`.
Tipler type-specific alan açar. Tanımlar kontrollü `PaymentMethod/PaymentType` konfigürasyonu ile genişletilebilir; business rule tek hard-coded listeye gömülmez.
POS komisyonu cariyi ikinci kez etkilemez.

### K-026 — Çek/Senet cari etkisi
- Müşteriden alınan çek/senet teslim/posting anında cari bakiyesini azaltır ve portföy kaydı oluşturur; bankada tahsil ikinci cari etkisi üretmez.
- Tedarikçiye verilen çek/senet teslim/posting anında borcu azaltır; bankadan ödeme ikinci cari etkisi üretmez.
- Karşılıksız/ödenmeme/iptal uygun reversal ile bakiyeyi yeniden açar.

### K-027 — B2B cari bağlantısı
B2B hesabı önceden bir Mars carisine bağlıdır. Siparişte cari seçilmez. B2B siparişi cari bakiyesini etkilemez; fatura etkiler.

### K-028 — E-Ticaret Integration Core ve doğrulanmış kanal kapsamı
Tek **E-Ticaret Integration Core** kullanılır; pazaryerleri ayrı business engine değildir.

**V1 doğrulanmış/resmî adapter seti:**
- WooCommerce
- Trendyol
- Hepsiburada
- Amazon Selling Partner API (SP-API; Türkiye marketplace öncelikli, region-aware)
- n11
- PttAVM
- idefix
- Allesgo
- Mars B2B

Bu set, geliştirmenin başlayabilmesi için API/dokümantasyon erişimi yeterince net olan kanallardır.

**Deferred / doğrulama bekleyen adaylar:**
- Çiçeksepeti
- Pazarama
- Koçtaş
- Teknosa
- Temu Türkiye
- Boyner

Deferred adaylar V1 teslim zorunluluğu değildir. Güncel resmî API dokümanı veya gerçek seller/partner erişimi doğrulanmadan adapter milestone'u açılmaz ve UI'da aktif kanal olarak sunulmaz.

Mars ürün/stok/temel fiyat/iç sipariş/fatura authority'sidir; external kanal operasyon/satış kanalıdır. Her aktif adapter ortak mapping, Inbox/idempotency, Outbox, retry/backoff, rate-limit ve problem-center kurallarını kullanır.

Provider capability'leri farklı olabilir. Bir kanalın API'sinde bulunmayan özellik emüle edilip varmış gibi gösterilmez; kanal capability matrix'i üzerinden ilgili aksiyon `supported / unsupported / manual` olarak yönetilir.

### K-029 — Credential yeri ve secret güvenliği
- Kanal API bilgileri: `E-Ticaret/B2B → Kanal Ayarları → Bağlantı`.
- SMS/E-Posta/WhatsApp/E-Belge: `Ayarlar → Entegrasyonlar`.
- Secret kaydedildikten sonra gerçek değer read-back yapılmaz; maskeli görünür, `Değiştir` ile yenilenir.

### K-030 — Cari edit/detail alan sahipliği
`Firma / Ticari`, `İletişim / Yetkililer`, `Sevk / Adres`, `B2B / Bayi Erişimi` duplicate sekmelere bölünmez. Cari detail aynı bilgileri readonly gösterir.

### K-031 — Cari bakiye terminolojisi
Kullanıcı-visible bakiye: `Alacaklı` yeşil, `Borçlu` kırmızı, sıfır `Bakiye Yok`.

### K-032 — Ürün detay ve medya
Ürün detail readonly, edit ayrı route'tur. Teknik Bilgi Dosyası ve Kurulum Kılavuzu ürün özelinde tutulur. Görseller site/kanal kullanım yerine göre bağımsız set/sıra/ana görsel taşıyabilir.

### K-033 — KDV Sıfırla
Satış Siparişi ve Satış Faturası satır alanında `KDV Sıfırla` tüm satır KDV oranlarını 0 yapar ve toplamları yeniden hesaplar.

### K-034 — API
External API `/api/v1` altında versionlanır. Eloquent public contract değildir; Resource/DTO kullanılır. Mutating API işlemleri authorization + idempotency + normalized request fingerprint uygular.

### K-035 — E-Belge ayrımı
Mars invoice lifecycle ile external e-document lifecycle ayrıdır. E-belge provider adapterı marketplace adapterından ayrıdır.

### K-036 — Data correction
Production direct SQL/tinker ile business edit yapılmaz. Controlled idempotent correction/reversal/adjustment command kullanılır ve audit edilir.

### K-037 — Schema evolution
Destructive one-shot yerine expand → compatible code → backfill → verify → switch → later contract tercih edilir.

### K-038 — Observability
Correlation ID HTTP → transaction → Outbox → job → provider zincirinde taşınır. Secret/raw sensitive payload varsayılan log değildir.

### K-039 — Backup/restore
Backup DB + dosyalar + gerekli config/manifest + checksum + release/schema bilgisini kapsar. **Restore drill başarıyla doğrulanmadan backup özelliği tamamlanmış sayılmaz.**

### K-040 — Negatif stok V1 politikası
V1'de fiziksel stok **negatife düşemez**. Posting/reservation işlemleri gerekli miktar yoksa BLOCK olur. Kullanıcı rolü business invariant'ı bypass edemez. Gerçek düzeltme ihtiyacı kontrollü stock adjustment/receipt/correction ile çözülür.

### K-041 — Satışta physical stock OUT authority
Varsayılan satış akışında **İrsaliye/Sevkiyat posting fiziksel stock OUT authority'sidir**.
- Sipariş → Sevkiyat → Fatura zincirinde stock OUT yalnız sevkiyatta oluşur; fatura ikinci kez stok düşmez.
- İrsaliyesiz/doğrudan satış faturası fiziksel çıkışı temsil ediyorsa invoice kendi stock OUT effect'ini üretir.
- Source lineage/unique effect key aynı fiziksel miktarın iki kez düşmesini engeller.

### K-042 — V1 stok maliyet yöntemi
V1 perpetual inventory valuation yöntemi **hareketli ağırlıklı ortalama (moving weighted average)**dır.
- carrying cost company + product seviyesinde deterministik hesaplanır,
- depo transferi taşıdığı değeri korur; transfer kâr/zarar yaratmaz,
- pozitif sayım adjustment'ında mevcut güvenilir moving-average kullanılır; yoksa explicit yetkili unit cost zorunludur,
- silent zero-cost pozitif stok yasaktır,
- return mümkünse original source cost lineage kullanır.

FIFO/standard-cost V1 core değildir.

### K-043 — Vergi / iskonto / fiyat giriş sözleşmesi
- Core `sale_price/purchase_price` normalize **KDV hariç net fiyat** olarak saklanır.
- Belge UI'sı company default'una göre `KDV Hariç` veya `KDV Dahil` giriş kabul edebilir; entered mode posted snapshot'ta saklanır ve net/tax deterministik hesaplanır.
- Satır ve belge iskonto etkisi KDV matrahından önce uygulanır.
- Belge geneli iskonto satırlara deterministik/oransal dağıtılır.
- KDV line-level hesaplanır; document total line toplamlarından oluşur; rounding difference explicit'tir.
- Sıfır KDV satırında `tax_zero_reason_code`/muafiyet gerekçesi tutulur; e-belge/provider mapping bu internal gerekçeden yapılır.

### K-044 — Cari para birimi V1
Her Account/Cari V1'de **tek book currency** taşır. Cari finans hareketi Account book currency ile aynı para biriminde olmalıdır; company base amount/rate snapshot ayrıca tutulabilir. Ham farklı para birimleri tek signed bakiye altında toplanmaz. Multi-currency cari bucket modeli future scope'tur.

### K-045 — Marketplace finansal clearing modeli
Marketplace siparişinde **legal/end-customer snapshot** ile **financial counterparty** ayrıdır.
- Marketplace invoice/order müşteri ad/adres/vergi/contact snapshot'ını taşır; her marketplace müşterisi için zorunlu Account açılmaz.
- Trendyol/Hepsiburada/Amazon/n11/PttAVM/idefix/Allesgo gibi marketplace hesaplarında finansal receivable varsayılan olarak kanal/account'a bağlı bir **Marketplace Clearing Account** üzerinde izlenir.
- Invoice clearing receivable oluşturur; payout/banka settlement clearing receivable'ı azaltır.
- Komisyon, hizmet, kargo, ceza/chargeback ve provider kesintileri ayrı fee/expense/settlement effect'tir; aynı tutar iki kez cariye yazılmaz.
- WooCommerce finansal settlement modu ödeme yöntemine göre `direct_account` veya `clearing_account` olabilir.
- Mars B2B pre-bound Account kullanır.

### K-046 — Treasury authority
Kasa/banka/POS parasal bakiye authority'si immutable/appended **`treasury_movements`** hareket defteridir. Collection/Payment/Expense/Transfer/POSSettlement gibi source kayıtları bu ledger'a deterministic source-effect üretir; doğrudan bakiye UPDATE edilmez.

### K-047 — Reservation / oversell V1
Reservation total, ilgili stok scope'unda `physical - blocked/quarantine` miktarını aşamaz. Explicit backorder V1 default değildir. Marketplace order importunda rezervasyon yapılamıyorsa negatif stok yaratılmaz; sipariş `Sorun/Stok Eksik` operasyon durumuna alınır. Kanal safety-stock oversell riskini azaltır fakat authority değildir.

### K-048 — Transit ve fason custody
Depolar arası kaynak çıkışı ile hedef kabulü arasında miktar/değer kaybolmaz; **in-transit custody** projection/ledger lineage ile şirket varlığı olarak izlenir. Fasona gönderilen company-owned malzeme de subcontract custody olarak aynı taşıma-değer ilkesini kullanır. Hedef kabul/gelen mamul reconcile edilmeden custody kapanmaz.

### K-049 — B2B authentication sınırı
External B2B kullanıcıları internal Mars User/RBAC hesabı değildir. Ayrı B2B auth context/guard kullanır; Account'a pre-bound'dur. Login/logout, activation/deactivation, password set/reset, rate-limit/session ve server-side B2B permission kontrolleri zorunludur. Internal admin permission'ları B2B token/session'a taşınmaz.

### K-050 — Gelecek özellik genişleme politikası
İleri özellikler bugünden yarım modül veya generic runtime plugin sistemi olarak kurulmaz. Gelecek geliştirme `27_GELECEK_GENISLEME_ALTYAPISI.md` içindeki seam/activation kuralına uyar.

Ortak seam'ler gerektiği ölçüde:
- provider family registry,
- typed capability,
- versioned internal event contract,
- stable external identity/source-effect,
- FeatureKey availability,
- import parser/report registry,
- Attachment processing/review pattern
olabilir.

İlk gerçek consumer yoksa bu seam için boş tablo/interface/framework ağı kurmak zorunlu değildir.

### K-051 — Product SKU ve future variant grouping
V1 `Product` satılabilir/stoklanabilir SKU authority'sidir. Marketplace parent/variant grouping Product stock/price/cost authority'sini değiştiremez.

Gerçek ihtiyaçta `ProductFamily/VariantRelation` additive olarak eklenebilir; family yalnız grouping/shared-content capability'sidir. Stock, price, barcode ve cost Product/SKU seviyesinde kalır.

## B. YAPILMAYACAKLAR
- Mikroservis, Event Sourcing, generic CQRS/BPM/hooks, GraphQL, EAV yok.
- Fatura bazlı cari settlement/OpenItem UX yok.
- Çoklu fiyat listesi yok.
- Core lot/seri yok.
- Generic QMS/PLM/ECO/OEE/shop-floor platformu yok.
- Generic report/document designer yok.
- Finansal binary float yok.
- Business truth Valkey'de yok.
- Canlı banka API/open-banking yok.
- İlk sürümde ayrı search service yok.
- Her pazaryeri için kopyalanmış ayrı sipariş/stok/fatura motoru yok.
- Doğrulanmamış marketplace API'si için tahmine dayalı production adapter yok.
- V1'de negatif stok bypass yok.
- Marketplace customer snapshot'ını zorunlu cari master'a dönüştürmek yok.
- Gelecek ihtimali için universal plugin loader/EAV/BPM altyapısı yok.
- AI/OCR/forecast çıktısının doğrudan finans/stok ledger authority olması yok.
- Gelecek lot/seri için bugünden her tabloya nullable tracking kolonları eklemek yok.

## C. Karar değiştirme
Locked karar değişirse sebep, data/migration etkisi ve etkilenen invariant/test/modüller aynı commit'te güncellenir. V16.3 tasarımına aykırı kullanıcı-visible davranış yeni onay olmadan eklenmez.

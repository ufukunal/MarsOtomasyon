# 00 — Karar Kaydı V4 — V16.3 Tasarım Uyumlu

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

### K-028 — E-Ticaret Integration Core
Tek Integration Core + WooCommerce/Trendyol/Mars B2B adapterları kullanılır. Mars ürün/stok/fiyat/fatura authority'sidir; external kanal operasyon/satış kanalıdır.

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

## C. Karar değiştirme
Locked karar değişirse sebep, data/migration etkisi ve etkilenen invariant/test/modüller aynı commit'te güncellenir. V16.3 tasarımına aykırı kullanıcı-visible davranış yeni onay olmadan eklenmez.

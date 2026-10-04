# MarsOtomasyon — Devir Promptu

## Rol

MarsOtomasyon için mimari, veri modeli, iş kuralı ve yerel-model görev dokümantasyonunu üret. Kullanıcı Türkçe konuşur. Kod daha sonra görev dosyalarından üretilecektir.

## Kaynak önceliği

1. Kullanıcının son açık kararı
2. GUNCELLEME-PROMPT.md
3. docs/05-karar-gunlugu/kararlar.md
4. docs/00-genel/07-veritabani-mimarisi.md
5. Güncel veri modeli / iş kuralı / görev belgeleri
6. Bu dosya
7. reference/marsotomasyon-PROTOTIP-ONAYLI-v65.html yalnız UI/terminoloji/görsel referans

## Kapsam

İçeride: ön muhasebe, cari, kasa/banka/çek-senet, stok, satış, alış, iade, ithalat, basit üretim, fason, e-ticaret.

Dışarıda: genel muhasebe, e-belge/GİB, lot/parti, bütçe, amortisman, ileri üretim/MRP/OEE, katalog ve kalite modülü.

## Teknoloji

PHP 8.3+, Laravel 13, Livewire 3, kendi UI bileşenleri, düz CSS, asgari JS, PostgreSQL, Valkey, Browsershot, spatie permission/activitylog/backup, Pest/Pint/Larastan. Filament/Tailwind/DevExpress/Stimulsoft kullanılmaz.

## DB mimarisi

`MarsProject_Master`: companies, periods, users, roles, permissions, company_user, şirket+dönem erişimleri, exchange_rates, app_settings, company_copy_permissions, print_profiles, master activity_log.

Her şirket+yıl ayrı period DB. Kartlar dahil işletme verisi period DB'dedir. Period tablolarında `company_id` ve şirket global scope'u yoktur. Kart-belge ilişkileri aynı period DB'de gerçek FK kullanır. Master users gibi cross-DB referanslar gerçek FK kullanmaz; scalar user_id + user_name snapshot tutulur.

## Teknik kilitler

- Money + BCMath; float yok.
- Yuvarlama belge toplam seviyesinde; rounding_difference saklanır.
- CHECK constraints.
- Idempotency key.
- `version` optimistic lock.
- `document_date`.
- Stok temel birimde; line: base_quantity + frozen conversion_factor.
- Eksik dönüşüm engeldir.
- `migrate:periods`.
- normalize search_index + trigram.
- DomainException loglanmaz; unexpected hata correlation id taşır.
- MIME içerikten doğrulama, UUID dosya adı, SVG yasak.
- Gerçek PostgreSQL test.
- Stok/cari bakiye cache edilmez.
- Türetilmiş/kopyalanmış her değer için integrity kontrolü.

## Satış ve cari kilitleri

- İrsaliye fiziksel sevk ve stok etkisidir, cari etkisi yoktur.
- Fatura cari borçlandırır. İrsaliyeden geliyorsa stok ikinci kez düşmez; doğrudan fatura stok+cari etkisi üretir.
- Satış satırı lokasyon taşıyabilir. Sipariş rezervasyonu mevcut stok kadar yapılır ve sistem gerekirse birden fazla lokasyona dağıtır.
- Teklif revizyonu ayrı immutable kayıt + aynı ana numara + Rev.N.
- Kısmi işlemde cancelled_quantity tekrar rezerv/sevk/fatura edilemez.
- Bir irsaliye bölünerek birden fazla faturaya; uyumlu irsaliyeler tek faturaya gidebilir.
- Cari bakiye contact_transactions toplamıdır; fatura-tahsilat eşleştirmesi zorunlu değildir.
- Yaşlandırma FIFO bilgilendirmedir; yeşil tam, sarı kısmi, kırmızı hiç kapanmamış.
- Çek/senet tesliminde cari etkisi oluşur; tahsil/ödeme tekrar cari etkilemez; karşılıksız/geri dönüş ters hareket üretir.
- Risk projeksiyonu cari bakiye + bu sipariş + henüz tahsil edilmemiş portföy kıymet riskini gösterir; blok yok.
- Liste fiyatından %20+ sapma uyarı+audit; blok yok.
- Satır ve belge iskontosu yüzde/tutar girilebilir.
- Manuel Cari Borç/Alacak Fişi gerekçe+audit ile vardır.

## Faz 4 alış kilitleri

- Belge ailesi esnektir: purchase_request, supplier_quote, purchase_order, goods_receipt, purchase_invoice; ara adımlar zorunlu değildir.
- goods_receipt stok/cari/maliyet etkisiz operasyon kaydıdır.
- purchase_invoice stock in + supplier credit + moving average üretir.
- Kısmi receipt/invoice ve kalan iptal desteklenir.
- Supplier payment Faz 5'tedir.
- Teklif karşılaştırma belge + satır bazlıdır; otomatik winner yoktur.
- Teklif seçimi `purchasing.quote.select` izniyle doğrudan yapılır; ayrı approval/eşik yoktur.
- Dövizli purchase invoice maliyet ve cari ledger etkileri frozen kurla şirket temel para birimine çevrilir.

## Faz 5 finans kilitleri

- Tüm kasa/banka virman kombinasyonları vardır; source/target aynı currency olmalıdır.
- Tedarikçi ödeme genel form + alış faturası kısayoluyla yapılır; settlement zorunlu değildir.
- Kasa sayımı toplam fiili bakiye ile yapılır; fark gerekçeyle ayrı adjustment hareketidir.
- İlk sürüm banka mutabakatı manueldir; ekstre importu/otomatik matching yoktur.
- Çek/senet tam kontrollü event yaşam döngüsü kullanır.
- Çek/senet geniş operasyon alanları taşır; ciroda karşı cari zorunludur.
- Faz 5 yeni FX dönüşüm/kur farkı motoru kurmaz.

## Faz 6 iade kilitleri

- Satış + alış iadesi birlikte kapsamdadır.
- Kaynaklı ve kontrollü kaynaksız iade vardır.
- Satış iadesi stock in + quarantine + customer credit üretir.
- Alış iadesi stock out + supplier debit üretir.
- Karantina kısmi karar destekler; physical quantity + quarantine birlikte izlenir.
- Satış iadesi kaynaklıysa original sales unit_cost, kaynaksızsa current moving average kullanır.
- Alış iadesinde kullanıcı current moving average veya source purchase cost seçer; kaynaksızda yalnız moving average.
- İade otomatik cash/bank hareketi üretmez.
- Kısmi/çoklu iade source-line toplamıyla sınırlandırılır.
- Kaynaklı iadede frozen fiyat/iskonto/KDV/unit/conversion; dövizli alış iadesinde original frozen kur kullanılır.
- Önceki dönem belge açık dönemde scalar source snapshot ile iade edilebilir; cross-DB FK yoktur.
- Kaynaksız iade reason + ayrı izin + audit ile manuel değer taşır.
- Ayrı approval state yoktur; return reason zorunludur.

## Faz 6 dokümantasyon sonucu

- Veri modeli 39: return_sources + quarantine_entries.
- İş kuralları 39–41.
- Ekranlar: satış iadesi, alış iadesi, karantina kontrolü, kaynak seçimi.
- G-600…G-609 hazırdır.
- Açık ürün kararı yoktur. A-125 K-257, A-126 K-258, A-127 K-256 ile kapatıldı.

## Faz 7 ithalat kilitleri

- Purchase invoice Faz 4 kurallarıyla ilk stok/maliyet etkisini üretir; import finalize yalnız ek maliyet adjustment yapar.
- Masraf dağıtımı alış değeri / miktar / manuel yöntemlerinden biridir.
- Sabit expense type listesi + other kullanılır; ağırlık/hacim ilk sürümde yoktur.
- Masraf kaynağı purchase_invoice veya cari etkisiz manuel import expense olabilir.
- İndirilebilir ithalat KDV'si stok maliyetine dahil edilmez.
- Farklı dövizli kaynaklar kendi frozen kurlarıyla base currency maliyet havuzunda birleşebilir.
- Purchase invoice line import dosyasına tam satır bazında bağlanır.
- Finalized import file immutable'dır; sonradan masraf ayrı import cost adjustment'tır.
- Miktar değiştirmeyen maliyet hareketinin gerçek kaynağı inventory_cost_adjustments'tır; zero-quantity stock movement yoktur.
- product_costs.import_cost son finalized import unit cost snapshot'ıdır; geçerli maliyet moving_average'dır.
- Import file kendi cari hareketini üretmez.
- Yaşam döngüsü draft → cost_collection → finalized → adjusted.
- Bir import file birden fazla purchase invoice/supplier içerebilir.
- Dağıtım rounding farkı son uygun satıra verilir.

## Faz 7 dokümantasyon sonucu

- Veri modeli 40: import_files, import_file_lines, import_expenses, import_expense_allocations, inventory_cost_adjustments.
- İş kuralları 42–44.
- Ekranlar: ithalat listesi, detay, masraf dağıtımı, late-cost adjustment.
- G-700…G-709 hazırdır.
- K-130: inventory cost adjustment unit farkı original import base_quantity üzerinden moving_average'a eklenir; current on-hand quantity formül paydası değildir.
- Açık ürün kararı yoktur. A-125 K-257, A-126 K-258, A-127 K-256 ile kapatıldı.

## Faz 8 üretim/fason kilitleri

- Faz 8 basit iç üretim + fason birlikte kapsamdadır.
- Bir mamulde tek aktif reçete, immutable Rev.N revizyonları vardır.
- Reçete output_quantity tabanlıdır; production order component/unit/conversion snapshot alır.
- Actual consumption kullanıcı tarafından değiştirilebilir; component-level fire manueldir ve mamul maliyetine dahil edilir.
- Component source location satır bazında; production output birden fazla target location'a bölünebilir.
- Production order kısmi tamamlanabilir; yaşam döngüsü draft → confirmed → in_progress → completed/cancelled.
- Completion anında component out + finished product in aynı transaction'dadır.
- Production cost actual material consumption maliyetidir; iç üretim overhead ilk sürümde yoktur.
- Production stock-in moving_average günceller; product_costs.production_cost son üretim maliyeti snapshot'ıdır.
- Fasoncu contact + subcontractor location modelidir; bu location normal satışta kullanılmaz.
- Fason hizmet bedeli normal purchase_invoice üzerinden production order'a bağlanır ve mamul maliyetine dahil edilir.
- Hizmet faturası geç gelirse inventory_cost_adjustments reason=subcontract_late_cost kullanılır.
- Fason gönderim/dönüş kısmi olabilir.
- channel_stock_mode=production confirmed satış siparişinde draft production order açabilir.
- Sales order → production order ilişkisi opsiyoneldir.
- Reçeteler dönem devrinde taşınır; açık production orders taşınmaz, fason location fiziksel stoğu açılışa taşınır.
- Production completion reverse edilebilir ve original immutable kalır.

## Faz 8 dokümantasyon sonucu

- Veri modeli 41: production recipes/orders/completions/consumptions/outputs + subcontractor location genişletmesi.
- İş kuralları 45–47.
- Ekranlar: reçeteler, üretim emri, completion, fason üretim.
- G-800…G-809 hazırdır.
- K-131…K-162 kilitlidir; açık A kararı yoktur.

## Faz 9 e-ticaret kilitleri

- Kanallar Trendyol, Hepsiburada, N11, WooCommerce; ortak adapter sözleşmesi vardır.
- Channel account/credential Master DB'de şirket bazındadır; aynı platformda çoklu mağaza olabilir.
- Listing mapping period DB'dedir; her internal varyant ayrı listing'dir.
- Product temel kaynaktır; listing override ve kanal→Ortak görsel fallback vardır.
- stock mode yalnız listing'e atanmış satışa uygun location kapsamını toplar.
- production/manual quantity listing bazındadır.
- External order idempotent confirmed sales_order olarak import edilir.
- Marketplace buyer/shipping snapshot'tır; import tahsilat üretmez.
- Cancel mevcut remaining-cancel, return draft sales_return akışıdır.
- Webhook birincil, 15 dk polling yedek; 30/60/120 retry + kalıcı sync history/error vardır.
- Mapping dönem devrinde taşınır; order/sync history taşınmaz.
- Dönemler arası external event duplicate engeli Master registry ile korunur.

## Faz 9 dokümantasyon sonucu

- Veri modeli 42: Master channel account/external event registry; Period listing/location/order snapshot/sync history.
- İş kuralları 48–51.
- Ekranlar: kanal hesapları, listing, stok/fiyat önizleme, kanal siparişleri, sync merkezi.
- G-900…G-910 hazırdır.
- K-163…K-201 kilitlidir; açık A kararı yoktur.

## Faz 10 rapor/çıktı kilitleri

- Geniş kapsam: operasyonel raporlar, dashboard, PDF/XLSX/CSV export, çok dönem, belge tasarımcısı, etiket/koli etiketi, print history.
- Raporlar mevcut kanonik transaction/stock/contact/finance tablolarını okur; ikinci bakiye kaynağı yaratmaz.
- cost.view maliyet/kâr kolonlarını query seviyesinde korur; reports.consolidated çok dönem erişimini korur.
- Çok dönem DB'ler ayrı sorgulanır, PHP'de birleştirilir.
- Belge tasarımcısı bölüm tabanlıdır; serbest drag/drop veya kullanıcı SQL/PHP/Blade kodu yoktur.
- Document/label templates Master DB'de şirket bazında immutable Rev.N'dir.
- PDF Browsershot; yazdırma tek PrintManager; ZPL/driver ayrıntısı soyutlama arkasındadır.
- Ürün etiketi ve ambar fişine bağlı koli etiketi desteklenir.
- Rapor kataloğu satış, alış, cari, stok, finans, çek/senet, iade, ithalat, üretim/fason ve e-ticareti kapsar.
- Genel muhasebe, resmi mali tablo, vergi/GİB raporları kapsam dışıdır.

## Faz 10 dokümantasyon sonucu

- Veri modeli 43: report presets/export jobs/document templates/print jobs/document print provenance.
- İş kuralları 52–56.
- Ekranlar: rapor merkezi, dashboard, çok dönem, export merkezi, belge tasarımcısı, etiket tasarımcısı, yazdırma geçmişi.
- G-1000…G-1012 hazırdır.
- K-202…K-235 kilitlidir; açık A kararı yoktur.
- G-1112 çok dönem raporu G-1006 ile aynı MultiPeriodQuery çekirdeğini kullanır.
- Genel muhasebe/GİB/resmi mali tablo kapsam dışıdır.

## Faz 11b ve Faz 11 dokümantasyon sonucu

- Faz 11b: G-1110…G-1113 hazır; carry preview/integrity/multi-period kapsamı günceldir.
- Açık quarantine taşınır; açık sales_order/purchase_order K-256 ile kalan miktar snapshot'ı olarak target'a aktarılır; open production/subcontract order ve in-transit transfer carry blocker'dır.
- Faz 11: veri modeli 44, iş kuralları 58–61, G-1101…G-1109 hazırdır.
- K-236…K-255 immutable deploy, backup/restore, health/security ve cutover/rollback davranışlarını kilitler.
- Tüm planlama/dokümantasyon fazları tamamlandı. Kodlama bu çalışma kapsamında yapılmıyor; sonraki çalışma kullanıcı talebine bağlıdır.

## Kodlama öncesi bütünlük blokajları

- A-125 KAPANDI → K-257: `document_lines.line_kind=stock|service`; service satır stok/moving-average üretmez, cari/KDV/toplama girer.
- A-126 KAPANDI → K-258: fason service cost completion miktarı oranında `production_service_allocations` ile dağıtılır.
- A-127 KAPANDI → K-256: açık sales_order/purchase_order yalnız kalan miktarla target period'da yeni confirmed snapshot olur; sales-order aktif rezervasyonları location bazında yeniden kurulur; teklif/taslak taşınmaz.
- Teknik olarak location alanı `kind`, production service invoice bağı `production_service_invoices`, subcontract late cost provenance `production_completion_id`, kanal-period marketplace customer eşlemesi `channel_account_period_settings` olarak düzeltilmiştir.
- Açık ürün kararı yoktur.

## K-256 açık sipariş dönem devri

- Açık sales_order/purchase_order yalnız kalan miktarlarıyla target period'da yeni confirmed order snapshot'ına dönüşür.
- Source period siparişi immutable/read-only kalır.
- Cross-period provenance period_document_carries ile tutulur.
- Sales-order aktif rezervasyonları location bazında yeniden kurulur.
- Teklifler ve taslaklar taşınmaz.

## Dönem devri

Aktif kartlar + gerekli pasif kartlar kopyalanır. Taşınan bütün kartların ID/kodları ve taşınan stock_balance ID'leri aynı şirkette korunur. Geçmiş hareketler/belgeler, açık teklif/taslak ve yoldaki transfer taşınmaz. Açık quarantine miktar/snapshot ile taşınır. K-256 gereği açık sales_order/purchase_order yalnız kalan miktarlarıyla target period'da yeni confirmed snapshot olarak oluşturulur; sales-order aktif rezervasyonları location bazında yeniden kurulur. Açılış maliyeti kapanış hareketli ortalamasıdır. Devir sonunda kullanıcıya önceki dönem kullanıcı/dönem erişim ve dönemsel yetkilerini yeni döneme seçerek kopyalama sorulur.

## Şirketler arası kopyalama

Master company_copy_permissions. Kaynak period_source. Hedef yeni ID; source_company_id + source_record_id provenance. Kod çakışmasında kullanıcı: mevcut kart / yeni kod / iptal. Otomatik overwrite yok.

## Faz ve görev yöntemi

Faz 0, 0b, 1, 2 görevleri standalone standarda göre temizlendi. **Faz 3–10, Faz 11b ve Faz 11 görev/dokümantasyon setleri hazırdır.** İleride kodlama yapılırsa her fazın gerçek PostgreSQL kabul testlerine bağlıdır.

Her görev şu bölümleri içerir: Amaç, Önkoşul, Dokunulacak dosyalar, Şema/Kod, Kurallar, Kabul ölçütü, İstem. Bir görev tek başına yerel modele verilebilir olmalıdır. **Satır sayısı hedef değildir.** 300–500 satır yalnız iş gerçekten o ayrıntıyı gerektiriyorsa doğal sonuç olabilir. Aynı genel checklist, mimari kural veya test maddesini sırf uzunluk için tekrar etmek yasaktır. Kaynaklarda tanımlanmayan alan, tablo, Action, sınıf, iş kuralı veya test beklentisi uydurulmaz. Eksik karar varsa `[KARAR GEREKİYOR]` yazılır ve kullanıcıya seçenek sunulur.

Açık ürün kararı yoktur. A-125 K-257, A-126 K-258, A-127 K-256 ile kapatıldı.


## Anti-halüsinasyon görev kuralı

- Görev dosyası yalnız repo kararları, veri modeli, iş kuralları ve onaylı prototipte desteklenen ayrıntıyı içerebilir.
- Ortak mimari kurallar her göreve kopyala-yapıştır doldurulmaz; yalnız o görevi doğrudan etkileyen maddeler yazılır.
- Test listesi yalnız görevin ürettiği davranışları test eder; görevle ilgisiz güvenlik/cache/queue/concurrency maddeleri eklenmez.
- Aynı kontrol maddesi farklı numaralarla tekrarlanmaz.
- Bir alanın adı, tipi veya davranışı kaynakta yoksa model tahmin etmez.
- “Muhtemelen gerekir” türü tahminler şemaya işlenmez; `[KARAR GEREKİYOR]` olarak ayrılır.
- Claude/yerel model görevi uygularken görev dosyasını genişletip yeni ürün kararı vermez.

## 03.10.2026 — 156 görev final kalite denetimi

- `docs/04-gorevler/` altındaki **156/156 G dosyası tek tek okundu**.
- **68 görev/özet dosyasında doğrudan kalite düzeltmesi** yapıldı; diğerleri mevcut kanonik sözleşmeyle uyumlu bulundu.
- Başlıca düzeltmeler: generic dosya kapsamları, G-115 Faz 4 scope taşması, sayım snapshot matematiği, quarantine gerçek kaynak ayrımı, K-257 mixed/service invoice zinciri, K-257/K-258 ithalat/fason çaprazları, K-256 kanal order carry provenance, G-1006↔G-1112 dairesel sahiplik, Faz 10 integrity sahiplik/test yayılımı, integrity:files/numbers acceptance zinciri, K-038 idempotency yayılımı ve stale production deploy akışı.
- Stale placeholder/`[KARAR GEREKİYOR]`/eski faz-onay metni kalmadı.
- Açık ürün kararı yoktur.
- Ayrıntılı rapor: `docs/00-genel/10-gorev-kalite-denetimi.md`.
- Kodlama yapılmadı.

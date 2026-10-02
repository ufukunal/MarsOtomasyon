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

## Dönem devri

Aktif kartlar + gerekli pasif kartlar kopyalanır. Taşınan bütün kartların ID/kodları ve taşınan stock_balance ID'leri aynı şirkette korunur. Geçmiş hareketler/belgeler/açık teklif-sipariş/taslak/yoldaki transfer/karantina taşınmaz. Açılış maliyeti kapanış hareketli ortalamasıdır. Devir sonunda kullanıcıya önceki dönem kullanıcı/dönem erişim ve dönemsel yetkilerini yeni döneme seçerek kopyalama sorulur.

## Şirketler arası kopyalama

Master company_copy_permissions. Kaynak period_source. Hedef yeni ID; source_company_id + source_record_id provenance. Kod çakışmasında kullanıcı: mevcut kart / yeni kod / iptal. Otomatik overwrite yok.

## Faz ve görev yöntemi

Faz 0, 0b, 1, 2 görevleri güncel standalone standarda göre temizlendi. **Faz 3 Satış dokümantasyonu yazıldı; G-300…G-312 hazırdır. Faz 4 Alış dokümantasyonu yazıldı; G-400…G-409 hazırdır.** Faz 3 iş kuralı dosyaları 28–31, Faz 4 yeni iş kuralı dosyaları 32–34 numaralarını kullanır. Faz 4 alış davranışları K-086…K-091 ile kilitlidir. Faz 5 Kasa/Banka/Çek-Senet dokümantasyonu kullanıcı onayıyla başlatılmıştır; G-500 açık kararları kapatılmadan Faz 5 davranışı uydurulmaz.

Her görev şu bölümleri içerir: Amaç, Önkoşul, Dokunulacak dosyalar, Şema/Kod, Kurallar, Kabul ölçütü, İstem. Bir görev tek başına yerel modele verilebilir olmalıdır. **Satır sayısı hedef değildir.** 300–500 satır yalnız iş gerçekten o ayrıntıyı gerektiriyorsa doğal sonuç olabilir. Aynı genel checklist, mimari kural veya test maddesini sırf uzunluk için tekrar etmek yasaktır. Kaynaklarda tanımlanmayan alan, tablo, Action, sınıf, iş kuralı veya test beklentisi uydurulmaz. Eksik karar varsa `[KARAR GEREKİYOR]` yazılır ve kullanıcıya seçenek sunulur.

Açık kararlar A-015…A-020'dir. Kullanıcıya seçenek sun; kendin kapatma.


## Anti-halüsinasyon görev kuralı

- Görev dosyası yalnız repo kararları, veri modeli, iş kuralları ve onaylı prototipte desteklenen ayrıntıyı içerebilir.
- Ortak mimari kurallar her göreve kopyala-yapıştır doldurulmaz; yalnız o görevi doğrudan etkileyen maddeler yazılır.
- Test listesi yalnız görevin ürettiği davranışları test eder; görevle ilgisiz güvenlik/cache/queue/concurrency maddeleri eklenmez.
- Aynı kontrol maddesi farklı numaralarla tekrarlanmaz.
- Bir alanın adı, tipi veya davranışı kaynakta yoksa model tahmin etmez.
- “Muhtemelen gerekir” türü tahminler şemaya işlenmez; `[KARAR GEREKİYOR]` olarak ayrılır.
- Claude/yerel model görevi uygularken görev dosyasını genişletip yeni ürün kararı vermez.

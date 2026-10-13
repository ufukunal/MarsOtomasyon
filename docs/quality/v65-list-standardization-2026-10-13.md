# MarsProject v65 — Bütün liste standardizasyonu: uygulama ve QA envanteri

**Tarih:** 2026-10-13  
**Baz commit:** `04bdfa37964648bcd0d86bceac82c4f47a229b1c`  
**Hedef:** `ufukunal/MarsOtomasyon` / `main`  
**Referans:** Onaylı v65 Ürün Listesi: başlık/aksiyonlar, arama/filtreler, kayıt sayısı, Satır/Kolonlar, 1–25/250, Önceki/Sonraki.

## Teknik gerçekleştirme

- Ortak `resources/views/components/v65/list-filters.blade.php` ve `list-footer.blade.php` oluşturuldu.
- `data-table.blade.php` satır seçimi ve filtre iş mantığı korunarak ortak footer'a bağlandı.
- `resources/css/v65-shell.css` içine izole `v65-list-*` kuralları, tablo satır/düğme hizalama, yatay scroll ve 1440/1024/768/390 genişlikleri için responsive kurallar eklendi.
- `app/Livewire/Concerns/WithV65DocumentList.php` satış + satınalma belge listelerini ortak arama, durum/tarih filtresi, kolon gizleme, 25/50/100 satır, CSV/XLSX ve mevcut döneme ait yetki kontrollü sorguya bağlar.
- Üretim emirleri, reçeteler, fason emirleri, ithalat dosyaları, kanal listingleri ve kanal sync olayları için gerçek server-side arama/filtreleme ve `paginate()` kullanımı eklendi.
- Ambar fişinin mevcut **basılı v65 şablonu ve işlem akışı değiştirilmedi**. Yalnız ambar listesinin filtre, tablo ve footer yapısı düzenlendi.
- Bağımsız merkezlerdeki işlem formu ve ilişkili iç tablo veri mutasyonları korunarak ortak tablo kaydırma ve görünüm kuralları uygulandı.

## Envanter ve uygulama kapsamı

| Grup | Route / ekran | Görünüm / altyapı | Kod değişikliği | Onaylı tarayıcı QA |
|---|---|---|---|---|
| Katalog | Ürün / Cari / Marka / Birim / Depo / Stok / Sayım / Transfer / Rezervasyon vb. | Ortak `DataTableComponent` | Ortak şablon değiştirildi | Bekliyor |
| Katalog | Kategori hiyerarşisi | `pages/catalog/category-list` | Üst aksiyon düzeni korumalı biçimde taşındı; ağaç görünümü istisna | Bekliyor |
| Satış | Teklif, sipariş, irsaliye, fatura, proforma | `sales/document-list` | Arama, durum/tarih, kolon, export, footer | Bekliyor |
| Satınalma | Alış siparişi, mal kabul, alış faturası | Aynı `document-list` | Yukarıdaki ortak veriyle | Bekliyor |
| Ambar | Ambar fişi listesi | `pages/warehouse/transport-slip-list` | Filtre ve footer, 25/50/100 | Bekliyor |
| Üretim | Emir, reçete, fason ana listesi | `production/*-center` | Filtre, sayfalama, tablo düzeni | Bekliyor |
| İthalat | Ana dosya listesi | `imports/import-center` | Arama, sayfalama, ana + alt tablolar | Bekliyor |
| E-ticaret | Listingler | `channels/channel-listing-center` | Arama, sayfalama ve tablo düzeni | Bekliyor |
| E-ticaret | Sync olayları | `channels/channel-sync-center` | Filtre, sayfalama, ikinci hata tablosu görünümü | Bekliyor |
| E-ticaret | Kanal hesapları | `channels/channel-account-center` | Tablo düzeni; liste küçük olduğundan koleksiyon korunuyor | Bekliyor |
| Finans | Kasa/banka, ekstre, çek-senet, cari yaşlandırma | `finance/*` | Alt liste görünümleri, iş akışları korunuyor | Bekliyor |
| İade | İade merkezi | `returns/return-center` | İç tablolar, iş akışı korunuyor | Bekliyor |
| Rapor | Rapor, dışa aktarım, baskı geçmişi, konsolidasyon, tasarım | `reporting/*` | Alt tablo görünümleri | Bekliyor |
| Yönetim | Dönem, devir, ayarlar, bütünlük, operasyon | `pages/settings/*` | Alt tablo görünümleri | Bekliyor |
| Diğer | Şirketler arası kopya, varyant, fiyat listesi, lokasyon detayı, ithalat önizleme | İlgili sayfa Blade'leri | İlgili liste/önizleme tablolarının stilleri | Bekliyor |

**Kaynak değişikliği sayacı:** Baz commit ile uygulama kodu arasında 35 farklı `resources/views/livewire/*.blade.php` dosyası değiştirilmiştir. Bu sayı “35 ayrı eksiksiz kullanıcı ekranı doğrulandı” anlamına **gelmez**. GitHub'daki tablo araması 46 Blade dosyası tespit etmiş; bunların bir bölümü saf liste değil, işlevsel düzenleme ekranındaki tablolar.

### Kasıtlı istisnalar

Aşağıdaki alanlar ana liste değil, işlem/düzenleme ekranıdır. Mevcut sözleşmeleri ve onaylı v65 ekran düzenleri korunmalı; liste iskeletine zorla dönüştürülmemeli:

- `sales/document-editor`, `purchases/document-editor`, `sales/proforma-detail`, `sales/vehicle-hot-sale`
- `pages/products/product-detail`, `pages/products/product-form`, `pages/contacts/contact-form`, `pages/catalog/unit-form`
- `pages/stock/stock-count-detail`, `pages/stock/warehouse-slip-detail`, `pages/stock/transfer-detail`, `pages/warehouse/transport-slip-editor`

Bu sayfaların gömülü tabloları mevcut `.data-table` stili ile desteklenmektedir. Özellikle bu ekranların iş akışlarında değişiklik yapılmadı.

## Test matrisi — ÇALIŞTIRILMADI

**Kodlama için eklenen kaynak denetimleri:** `tests/Unit/UI/V65ListStandardizationTest.php`.

Test yürütme için ayrıca onay gerekli. Plan:

1. `php -l` / Laravel Blade derleme / Vite asset build / test kaynak tutarlılığı.
2. PHP UI sözleşme testleri, Livewire filtre/sayfalama testleri.
3. Şirket/dönem ayrımı ve salt yetkili export sonuçları.
4. Arama, çoklu filtre, kolon gizle/göster, 25/50/100, sonraki/önceki, boş ve çok satırlı listeler.
5. CSV / XLSX formül enjeksiyonu, yetki matrisi, belgenin durum/numara/tutar korunumu.
6. 1920, 1440, 1024, 768 ve 390 pikselde browser görsel karşılaştırma.
7. Onaylı v65 ambar baskısı, stok mutasyonları, fatura/üretim kesinleştirme regresyonlarının korunumu.
8. Fresh UAT'ye ayrı onayla sadece web imajı yayını ve tarayıcı smoke kontrolü.

## Durum

- **Kaynak kod değişiklikleri:** GitHub `main` içine işlendi.
- **Birim ve entegrasyon testleri:** **ÇALIŞTIRILMADI — onay bekliyor.**
- **Görsel browser QA:** **ÇALIŞTIRILMADI.**
- **Fresh UAT / PROD:** **MÜDAHALE EDİLMEDİ.**
- **Canlı kabul:** **VERİLMEDİ.**

Açık riskler: Bazı bileşik operasyon ekranları özel koleksiyon ve alt işlemler kullanır; hepsine aynı tür kolon seçici veya 25/50/100 getirilmesi bu ekranların düzenini bozacağından görsel standardizasyonla sınırlı tutuldu. Çalıştırılmamış kodun regresyonsuz olduğu iddia edilmemelidir.

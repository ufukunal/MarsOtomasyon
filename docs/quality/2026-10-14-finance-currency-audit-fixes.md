# MarsProject — Finansal İşaret ve Döviz Ayrımı: Düzeltme Kaydı

Tarih: 2026-10-14

## Amaç

Cari Borçlu/Alacaklı düzeltmesinin ardından repo genelinde tespit edilen 8 finansal tutarlılık bulgusunu kaynak kodunda gidermek. Bu çalışma **veritabanı migrasyonu veya mevcut finansal harekete UPDATE/DELETE** içermez. V65 arayüzü ve muhasebe yönleri korunmuştur.

## Tamamlanan kaynak değişiklikleri

| # | Bulgu | Düzeltme | Kaynak |
|---|---|---|---|
| 1 | Cari Yaşlandırma TRY, USD vb. bakiyeleri topluyordu | Sorguya tek para birimi filtresi, sonuçlara currency alanı, ekrana para birimi seçimi; integrity kontrolü her carinin her dövizini ayrı inceliyor | `BuildContactAging.php`, `ContactAgingResult.php`, `ContactAging.php`, `contact-aging.blade.php`, `ContactBalanceCheck.php` |
| 2 | Satış siparişinde döviz risk hesabı TRY kabul ediliyordu | `CalculateSalesRiskProjection` TRY dışındaki siparişte doğrulanmamış kurla hesap yapmadan açıklayıcı istisna üretir. TRY risk limiti olan döviz satış faturasının post edilmesi de güvenli dönüşüm olmadığında engellenir | `CalculateSalesRiskProjection.php`, `PostSalesInvoice.php`, `document-editor.blade.php` |
| 3 | Fatura, cari, kasa/banka ve kanal raporu farklı döviz tutarlarını topluyordu | Tutarlar her para biriminde `GROUP BY currency` ile hesaplanır; filtre belirtilmezse her döviz kendi koduyla görüntülenir, TRY filtreli raporda önceki sayısal çıktı korunur | `CurrencySeparatedTotals.php`, dört rapor sorgusu |
| 4 | Dönem devrinde açık bakiyeler dövizler arasında birbirini sıfırlayabiliyordu | Cari kimliği + para birimi bazında net bakiye değerlendiriliyor | `CopyPeriodCards.php` |
| 5 | Devir önizlemesi finans hareketlerini farklı dövizlerle topluyordu | Cari para birimini hareketten; kasa/banka para birimini bağlı hesaptan alarak döviz bazlı tutar listesi üretir | `PreviewPeriodCarry.php` |
| 6 | Dashboard toplamlarının bakış açısı belirsizdi | Şirket açısından `Carilerden Alacaklarımız / Carilere Borçlarımız` etiketleri | `dashboard.blade.php` |
| 7 | Yaşlandırmada teknik durum ve dövizsiz tutar gösteriliyordu | TRY/USD seçici; bakiye/açık borç/fazla alacak için döviz kodu ve Kapandı/Kısmen kapandı/Açık etiketleri | `contact-aging.blade.php` |
| 8 | Kesinleşmiş demo faturasında TASLAK hazırlık notu eski haliyle gösteriliyordu | Yalnız kullanıcıya gösterilen açıklamada güncel belge durumu belirtilir; geçmiş açıklama veritabanında korunur. Ekstre CSV de bu açıklamayı kullanır | `ContactDetail.php`, `contact-detail.blade.php` |

### Bağlı rapor entegrasyonu

`MultiPeriodQuery.php` farklı dönemlerin yeni döviz etiketli toplamlarını da aynı para biriminde birleştirir. Geçersiz biçim veya dövizsiz sıfır dışı tutarın dövizli tutarla birleştirilmesi reddedilir. Sayısal tek para birimli raporlar önceki toplama mantığını korur.

### Eklenen testler (hazırlandı, çalıştırılmadı)

- `tests/Unit/Reporting/CurrencySeparatedTotalsTest.php`: TRY/USD ayrımı, açık seçilmiş TRY raporu, geçersiz para birimi.
- `tests/Integration/ContactAgingCurrencyIsolationTest.php`: farklı dövizde 100 debit ve 100 credit hareketlerinin birbirini kapatmaması; USD sipariş riskinin reddi.
- `tests/Unit/Reporting/FinanceCurrencyBoundaryContractTest.php`: rapor sorguları, dönem devri, devir önizlemesi ve demo gösterim kontratları.
- `tests/Unit/Reporting/MultiPeriodAggregationRulesTest.php`: EUR/TRY/USD toplamların dönemler arasında kendi dövizinde birleştirilmesi.

## Sınırlamalar ve yayın kapıları

1. Testler, PHP/Blade/Vite derleme, PostgreSQL entegrasyon kontrolleri ve tarayıcı kabulü **çalıştırılmadı**.
2. Kaydedilmiş EUR/USD satış siparişi ile TRY risk limitini doğru karşılaştıracak doğrulanmış kur/kilit prosedürü yoksa işlem güvenli şekilde reddedilir; dönüşüm yapılmış olduğu iddia edilmez.
3. GitHub kaynak düzeltmeleri çalışan Fresh UAT/PROD konteynerlerine otomatik yansımıyor.
4. Raporun döviz filtresi yoksa toplam alanı artık tek sayı değil; `100 TRY / 20 USD` gibi para birimi açıkça yazan metindir. Export ve multi-period ekranlarda gerçek veriyle kabul gereklidir.
5. Önceki demo hareketlerinin doğruluğu PostgreSQL üzerinde ayrıca salt-okunur denetlenmelidir; veri düzeltme işlemi yapılmamıştır.
6. Özellikle `ContactBalanceCheck`, dönem devri ve post işlemlerinde test onayı alındıktan sonra izole PostgreSQL üzerinde regresyon testleri çalıştırılmalıdır.

## Koruma ilkesi

Mevcut e-fatura, satış/alış, tahsilat, iade, cari bakiyesi, reversal ve defter hareketlerinde DB tarafında işaret/tutar/direction değişikliği yapılmamıştır.

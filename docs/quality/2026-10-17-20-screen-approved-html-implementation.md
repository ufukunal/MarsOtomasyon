# MarsProject · 20 ekran HTML kaynak eşleşme çalışması · 17.10.2026

Bu belge 17 Ekim tarihinde verilen **MarsProject_20_Ekran_Cari_Virman_Tek_HTML.html** dosyasını kaynak kabul eder. Kaynak **yalnızca etkileşimli tasarım prototipidir**; gerçek mali kayıt, stok veya güvenlik doğrulaması yerine geçmez.

## Birebir karşılaştırma matrisi
| No | Referans ekran | Laravel ekranı | Kod çalışması | Görsel kabul |
|---:|---|---|---|---|
| 01 | Satış Teklifi | sales.quotes.edit | Üst form alanları, teklif süresi/şartı | Bekliyor |
| 02 | Satış Siparişi | sales.orders.edit | Planlanan teslim | Bekliyor |
| 03 | Satış İrsaliyesi | sales.dispatches.edit | Ortak V65 belge editörü | Bekliyor |
| 04 | Satış Faturası | sales.invoices.edit | Vade, ödeme şekli | Bekliyor |
| 05 | Alış Teklifi | purchases.quotes.edit | Ortak V65 belge editörü | Bekliyor |
| 06 | Satınalma Siparişi | purchases.orders.edit | Ortak V65 belge editörü | Bekliyor |
| 07 | Alış İrsaliyesi | purchases.dispatches.edit | Kabul deposu alanı | Bekliyor |
| 08 | Mal Kabul | purchases.receipts.edit | Bilgi kartı, kabul tarihi, kaynak, irsaliye | Bekliyor |
| 09 | Alış Faturası | purchases.invoices.edit | Ortak V65 belge editörü | Bekliyor |
| 10 | Tahsilat | finance.collections.create | Form ve ayrı cari hareket özeti | Bekliyor |
| 11 | Tediye | purchases.payments.create | Form ve ayrı cari hareket özeti | Bekliyor |
| 12 | Cari Virman | finance.contact-transfers | Üst bilgi formu, iki bakiye kartı, mali etki, geçmiş | Bekliyor |
| 13 | Çek Listesi | finance.checks.index | Liste, durum filtreleri, işlemler | Bekliyor |
| 14 | Alınan Çek | finance.checks.incoming | Ayrı form, banka bilgisi | Bekliyor |
| 15 | Verilen Çek | finance.checks.outgoing | Ayrı form, banka hesabı | Bekliyor |
| 16 | Çek Ciro | finance.checks.endorsement | Evrak, hedef cari ve ciro açıklaması | Bekliyor |
| 17 | Senet Listesi | finance.notes.index | Liste, durum filtreleri, işlemler | Bekliyor |
| 18 | Alınan Senet | finance.notes.incoming | Ayrı form, lehtar bilgisi | Bekliyor |
| 19 | Verilen Senet | finance.notes.outgoing | Ayrı form, lehtar bilgisi | Bekliyor |
| 20 | Senet Ciro | finance.notes.endorsement | Evrak, hedef cari ve ciro açıklaması | Bekliyor |

## Korunan işlem ilkeleri
- Kesinleşmiş belgeler yerinde değiştirilmez; iptal, ters kayıt veya revizyon mekanizmaları korunur.
- Cari virman yalnızca cari defter hareketleri oluşturur; kasa/banka etkisi sıfırdır.
- Çek/senet ciro işlemi bağımsız bordro hareketi yaratır, bağlantılı banka hareketi yalnız ilgili kayıt aşamasında oluşur.
- Yetki kontrolü, idempotency ve dönem kilidi kullanıcı arayüzüyle devre dışı bırakılamaz.
- Satış/satınalma ürün tabloları ve server-side KDV/iskonto hesaplamaları değiştirilmez.
- E-belge entegrasyonu eklenmez.

## Kabul engelleri
1. Bu değişikliklerin yeni commit'inde **PHP/Blade, Pint, PHPStan ve kaynak kontrat workflow sonucu henüz yoktur**.
2. Fresh UAT yalnız tam kaynak SHA, izole veritabanı, iki yeni kolon ve rol izni doğrulandıktan sonra güncellenmelidir.
3. Canlı UAT üzerinde 20 ekranın 1440px ve 390px görsel karşılaştırması, yeni kayıt, mevcut kayıt düzenleme ve finansal hareket sonuçları yapılmalıdır.
4. Bu aşamada production Docker dağıtımı, PostgreSQL migration yürütmesi ve UAT kabulü yapılmadı.

Statü: **GitHub kaynak düzenlemesi; test ve ekran kabulü açık.**
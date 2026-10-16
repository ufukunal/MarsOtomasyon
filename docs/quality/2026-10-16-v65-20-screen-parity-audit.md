# MarsProject 20 ekran prototipi — kaynak bütünlük haritası (16.10.2026)

Referanslar:
- `reference/marsotomasyon-PROTOTIP-ONAYLI-v65.html` (onaylı görsel dil ve ortak kabuk)
- Konuşmada onaylanan `MarsProject_20_Ekran_Kayit_Duzenleme_Tek_HTML.html` (20 operasyon ekranı + giriş ekranı; repository içinde bulunmayan dış HTML prototipi)
- `routes/web.php` ve `config/navigation.php` (gerçek uygulama)

**Kapsam:** Bu belge kaynak kodda varlık/rota/işlem sınırını eşleştirir. Piksel bazlı veya canlı veritabanı kabulü değildir.

| # | 20 HTML ekranı | Laravel rota adı | Ekran / temel işlem | Kaynak kontrolü |
|---:|---|---|---|---|
| 1 | Satış Teklifi (`satis-teklifi`) | `sales.quotes.index` | `QuoteEditor` | Taslak düzenleme / onay |
| 2 | Satış Siparişi (`satis-siparisi`) | `sales.orders.index` | `SalesOrderEditor` | Taslak düzenleme / kesinleşme akışı |
| 3 | Satış İrsaliyesi (`satis-irsaliyesi`) | `sales.dispatches.index` | `DispatchEditor` | Taslak düzenleme / stok geçişi |
| 4 | Satış Faturası (`satis-faturasi`) | `sales.invoices.index` | `SalesInvoiceEditor` | Taslak / ters kayıt |
| 5 | Alış Teklifi (`alis-teklifi`) | `purchases.quotes.index` | `PurchaseQuoteEditor` | Teklif onayı ve siparişe dönüşüm |
| 6 | Satınalma Siparişi (`alis-siparisi`) | `purchases.orders.index` | `PurchaseOrderEditor` | Taslak / onay |
| 7 | Alış İrsaliyesi (`alis-irsaliyesi`) | `purchases.dispatches.index` | `PurchaseDispatchEditor` | Sevk takibi, stok sadece mal kabulde |
| 8 | Mal Kabul (`mal-kabul`) | `purchases.receipts.index` | `GoodsReceiptEditor` | Sipariş / irsaliye referansı |
| 9 | Alış Faturası (`alis-faturasi`) | `purchases.invoices.index` | `SupplierInvoiceEditor` | Taslak / ters kayıt |
| 10 | Tahsilat (`tahsilat`) | `finance.collections.create` | `CollectionForm` | Bakiye, arama, tersleme ardından düzeltme |
| 11 | Tediye (`tediye`) | `purchases.payments.create` | `PaymentForm` | Bakiye, arama, tersleme ardından düzeltme |
| 12 | Cari Virman (`cari-virman`) | `finance.contact-transfers` | `ContactTransferForm` | Bakiye aşımı onayı, çift cari ters kayıt |
| 13 | Çek Listesi (`cek-listesi`) | `finance.checks.index` | `CheckListScreen` | Statü bazlı liste |
| 14 | Alınan Çek (`cek-alinan`) | `finance.checks.incoming` | `IncomingCheckScreen` | Portföy yaratma |
| 15 | Verilen Çek (`cek-verilen`) | `finance.checks.outgoing` | `OutgoingCheckScreen` | Verilen evrak |
| 16 | Çek Ciro (`cek-ciro`) | `finance.checks.endorsement` | `CheckEndorsementScreen` | Onaylı ciro |
| 17 | Senet Listesi (`senet-listesi`) | `finance.notes.index` | `NoteListScreen` | Statü bazlı liste |
| 18 | Alınan Senet (`senet-alinan`) | `finance.notes.incoming` | `IncomingNoteScreen` | Portföy yaratma |
| 19 | Verilen Senet (`senet-verilen`) | `finance.notes.outgoing` | `OutgoingNoteScreen` | Verilen evrak |
| 20 | Senet Ciro (`senet-ciro`) | `finance.notes.endorsement` | `NoteEndorsementScreen` | Onaylı ciro |

## Düzenleme ve muhasebe bütünlüğü

- Taslak belgeler yetkili kullanıcı tarafından açılır, kaydedilir; kesinleşen belgeler doğrudan değiştirilmez.
- Tahsilat/tediye kayıtları için `WithFinancialRecordCorrection`: geçmişten seç → karşıt cari/kasa/banka hareketi oluşturan ters kayıt → yeni kayıt.
- Çek/senet vade ve açıklama değişiklikleri, bordro görmemiş `portfolio|issued` evraklarda versiyon kontrolüyle yapılır; tutar, banka, cari ve evrak no değiştirilmez.
- Virman kaynak/hedef bakiyeleri eşit-zıt etkilenir; aşım yetkisi ve ters kayıt ayrı kontrol edilir.
- Evrak tutarı, kaynak cari ve banka gibi mali alanların güvenli değiştirilmesi için ilave reverser/re-issue iş akışı hâlâ gereklidir.

## Kabulde açık kalan somut farklar

1. Onaylı v65 ile tüm 20 ekranın **tarayıcıda piksel ve responsive** karşılaştırması Fresh UAT üzerinde yapılmadı.
2. Çek/senet ilk kaydının **mali alan düzenlemesi** bulunmuyor; mevcut iş kuralları için ters işlem geliştirilmeden yerinde değişiklik yasak.
3. Virman ve çek/senet dahil PostgreSQL-backed uçtan uca senaryolar izole TEST altyapısında çalıştırılamadı; yalnızca kaynak kontratı teyidi vardır.
4. `MARS_V4_DB_PASSWORD`, `MARS_V4_VALKEY_PASSWORD` test güvenlik kapısı doğrulanmalı; PROD sırlarıyla kestirme yapılmamalı.
5. Fresh UAT migration ve rol atama senkronizasyonu, yedek ve rollback önkoşulu sağlanmadan yapılmamalıdır.
6. Sadece web konteyneri güncellemesi, iki yeni `period` migration'ı olmadan başarısız olabilir; update scriptinde şema kontrolü vardır.

## Yayın kararı

**Kod kaynaklı ekran eşleştirmesi: 20/20 rota. Üretim kabulü: henüz yok.** Fresh UAT canlı sunucu smoke, finansal etki testleri ve kullanıcı imzası olmadan PROD'a yayın yapılmaz.

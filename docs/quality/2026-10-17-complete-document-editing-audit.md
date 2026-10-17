# MarsProject — tüm belge türlerinde düzenleme erişimi denetimi

Tarih: 2026-10-17
Başlangıç GitHub main: `ac8f4cea4d162ccb3c5901531eef28cae99b6f72`

## Kapsam / veri kaynağı

`app/Enums/DocumentType.php` toplam 18 kalıcı ERP belge türü tanımlar.
Ayrıca `WarehouseSlip`, `Transfer`, `StockCount`, `TransportSlip`,
çek/senet, cari virman ve üretim/emir kayıtları aynı `documents` tablosu
dışındaki gerçek iş akışlarına sahiptir.

Yapılan çalışma yalnız mevcut PHP/Laravel kaynak kodu ve GitHub dosyaları
üzerinden gerçekleştirilmiştir. İlgili UAT/production veritabanlarında test
çalıştırıldığı anlamına gelmez.

## 18/18 belge düzeltme rotası

| Belge | GitHub rota | Mevcut doğru düzenleme yöntemi |
| --- | --- | --- |
| Satış teklifi | sales.quotes.edit | Taslak / revizyon |
| Satış siparişi | sales.orders.edit | Taslak / bağımsız düzenleme kopyası |
| Satış irsaliyesi | sales.dispatches.edit | Taslak / kesinleşmede ters kayıt ardından düzeltme |
| Satış faturası | sales.invoices.edit | Taslak / ters kayıt ardından düzeltme |
| Proforma | sales.proformas.show | Kaynak teklif / siparişte revizyon; eski proforma görüntüsü kalır |
| Tahsilat | finance.collections.create | Seçili gerçek kayıtla ters kayıt ve yeniden tahsilat |
| Cari borç / alacak fişi | finance.contact-debit-credit | Seçili fişle ters kayıt, düzeltilmiş fiş |
| Alış teklifi | purchases.quotes.edit | Taslak / bağımsız düzeltme taslağı |
| Satınalma siparişi | purchases.orders.edit | Taslak / bağımsız düzeltme taslağı |
| Alış irsaliyesi | purchases.dispatches.edit | Mal Kabul yoksa yerinde taslağa yeniden açma |
| Mal Kabul | purchases.receipts.edit | Taslak / kesinleşmede stok ters kaydı ve yeni Mal Kabul |
| Alış faturası | purchases.invoices.edit | Taslak / kesinleşmede ters kayıt ve yeni fatura |
| Tediye | purchases.payments.create | Seçili gerçek kayıtla ters kayıt ve yeni tediye |
| Gider belgesi | finance.operations | Seçili gerçek kayıtla ters kayıt ve yeni gider |
| Avans | finance.operations | Seçili gerçek kayıtla ters kayıt ve yeni avans |
| Avans iadesi | finance.operations | Seçili gerçek kayıtla ters kayıt ve yeni iade |
| Satış iadesi | returns.center | Taslakta satır düzeltme; posted kayıtta stok/cari ters kaydı |
| Alış iadesi | returns.center | Taslakta satır düzeltme; posted kayıtta stok/cari ters kaydı |

## Eksik erişim noktaları ve kaynak güncellemeleri

- Genel belge listeleri sekiz ticari tipte açık; finans, iade, proforma
  ve stok belgelerini tek yerden arama/düzeltme ekranı yoktu.
- `DocumentEditCatalog` kullanıcı izinlerine göre 18/18 kayıt türünü
  filtreler; kaynak rota ile düzeltme yöntemini merkezileştirir.
- `DocumentEditCenter` kayıtları PostgreSQL'de filtreler, 25'li sayfalar
  ve ayrıştırılabilir `ara`, `tur`, `durum` URL parametreleri sağlar.
- Finans merkezine ve iade merkezine `belge=ID` derin bağlantısı,
  gerçek belge tipi/durum/yetki kontrolünü tekrar uygulayan mevcut
  `selectForCorrection`, `prepareCorrection`,
  `prepareDocumentCorrection`, `selectReturn`, `editDraft`
  metotlarıyla bağlandı.
- Önceden terslenmiş kayıt için bir kez daha ters kayıt açtırılmaz;
  kullanıcı ilgili işlem geçmişine yönlenir.
- Salt görüntüleme yetkili kullanıcıda iptal/yeniden oluşturma
  yetkisi yoksa derin bağlantı yeni düzeltme başlatmaz.
- Belge tablosu dışında kalan 11 gerçek iş akışının (stok fişi,
  transfer, sayım, ambar, çek, senet, cari virman, kasa/banka,
  üretim, fason, ithalat) var olan ekranlarına izinli bağlantılar
  eklendi. Kaynak belgeye yeni sahte CRUD oluşturulmadı.

## Hesap/muhasebe güvenlik kuralları

- Fatura, tahsilat, tediye, iade, Mal Kabul gibi kayıtların
  posted mali ve stok hareketleri **yerinde değiştirilemez**.
- İzinli kullanıcının düzenleme seçeneği olması, otomatik olarak
  ters kayıt yürütme veya iptal etme izni vermez.
- Kaynak belgenin eski tarih/numara, orijinal bakiye, rezervasyon,
  depo kabul, ciro veya audit geçmişi silinmez.
- Çek/senet mali düzeltme ayrı `ReplaceSecurity` aksiyonunda,
  cari virman ters kaydı ayrı `PostContactTransfer` akışında tutulur.
- Proforma anlık kaydına sessiz değişiklik yapılmaz;
  kaynak belge revizyonundan yeni proforma düzenlenir.

## Bağımsız kabulde açık konular

1. Gerçek `php -l`, Laravel `view:cache`, PHPStan, Pint:
   bu geliştirme turunda **çalıştırılmadı**.
2. PostgreSQL izole TEST veritabanı 18 belge türü ve stok/defter
   ters kayıt senaryoları: **çalıştırılmadı**.
3. Yetkisiz hesabın satırları göremediği, derin bağlantıyla
   iptal isteği gönderemediği ve terslenmiş kaydın tekrar
   düzeltmeye alınmadığı UAT testi: **çalıştırılmadı**.
4. GitHub kaynak kod yayını ve production Docker/period migration
   dağıtımı **ayrı aşamalardır**.

Manuel kalite kontratı:
`tests/Unit/UI/MarsAllDocumentEditingContractTest.php`.


## 17 Ekim ek doğrulama — finansal kaynak hareketinden düzenlemeye geçiş

- `main` üzerinde `564aea9cb4facf90084d23bffc15739550820aa5` commit'inin
  18 DocumentType için merkezi düzenleme kataloğunu ve 11 ek modül
  yönlendirmesini içerdiği doğrulandı.
- Stok hareketi ve rezervasyon detaylarından finansal kaynak belgeye yapılan
  navigasyonda `DocumentEditNavigation::url()` yalnız liste URL'si
  döndürüyordu. Oysa tahsilat, tediye, cari borç/alacak, gider,
  avans, avans iadesi ve iadeler `?belge=ID` ile kayıt seçebilen
  düzeltme ekranları kullanıyor.
- Düzeltme: URL, aynı türdeki gerçek period `documents` kaydı üzerinden
  ve `DocumentEditCatalog::url()` yardımıyla oluşturulur. Yanlış kaynak
  ID/tür eşleşmesinde bağlantı üretilmez. Yetkisi olmayan veya
  zaten terslenmiş kayıt için otomatik düzeltme başlatılmaz.
- Cari borç/alacak fişi kaynağına navigasyon izni, formun gerçek
  `contacts.update` yetkisi ile eşlendi; yalnız `contacts.view`
  olan kişiye yetkisiz düzenleme ekranı bağlantısı verilmez.
- Kaynak kontratı `MarsAllDocumentCorrectionNavigationTest.php` ile
  genişletildi. Bu kontrollerin kaynakta bulunması PHP/Pest testinin
  GitHub Actions'ta başarıyla çalıştığı anlamına gelmez.
- `php -l`, `artisan view:cache`, PHPStan/Pint, izolasyonlu PostgreSQL,
  mali ters kayıt kabulü ve production Docker dağıtımı bu ek yamada
  gerçekleştirilmedi.


## 17 Ekim — bütün belgeler için tekil kayıt geçmişi yayını

- Ana dalda `DocumentEditCatalog::entries()` ile 18/18 belge tipi ve ilgili
  modüllerdeki mevcut düzeltme akışları yeniden karşılaştırıldı.
- Önceki davranış: ters kaydı bulunan veya düzeltme yetkisi bulunmayan
  tahsilat, tediye, cari fişi, avans, gider ve iade belgesinin bağlantısı
  çoğu zaman sadece genel listeyi açıyordu; özgün belge seçili değildi.
- Yeni `documents.history` rotası `/belgeler/gecmis/{id}`, tekil belgeyi
  **salt okunur** gösterir: numara, tür, durum, toplam, kalemler ve izlenebilen
  kaynak/revizyon/ters kayıt bağlantıları.
- `DocumentHistoryDetail` her render sırasında kayıt türü ve kullanıcı
  `.view` iznini yeniden doğrular. `#[Locked]` document ID ile istemcinin
  diğer period kaydı seçmeye çalışması engellenir. Bağlı belge kayıtları yalnız
  kendi `.view` yetkileri olduğunda gösterilir. Cari unvanı `contacts.view`
  izni olmadan yazdırılmaz.
- Mevcut belge düzenleme merkezinde **Kayıt Geçmişi**, ortak satış/alış
  listesinde **Geçmiş**, sekiz belge formunda ve Mal Kabul başlığında
  **Kayıt Geçmişi** bağlantısı eklendi.
- `DocumentEditCatalog::url()` durum/yetki nedeniyle değişiklik yapamayan
  finans/iade kayıtlarının URL'sini kayıp belge kimliği ile genel modüle
  göndermek yerine tekil geçmiş sayfasına yönlendirir.
- Proforma için `Kaynakta Düzenle` etiketinin doğrudan proforma üzerinde
  finansal değişiklik yapılabildiği izlenimini vermemesi sağlandı;
  proforma anlık görüntüsü ve kaynak üzerinden revizyon yaklaşımı korunur.
- PostgreSQL defteri ve stok hareketleri için yeni mutasyon eklenmedi.
  Tüm 18 tip için `source/reverse/revision/draft` ayrımı aynen korunuyor.
- Kaynak regresyonu: `tests/Unit/UI/MarsExactDocumentHistoryContractTest.php`,
  manuel `v65-source-quality.yml` listesine dahil edildi.
- Statik PHP blok ve Blade yönerge kontrolleri yapıldı; **gerçek**
  `php -l`, Laravel `view:cache`, PHPStan, izole PostgreSQL ve UAT
  ekran testleri bu adımda çalıştırılmadı.
- Production veya Fresh UAT Docker dağıtımı yapılmadı; kod GitHub
  `main` yayını ile sınırlıdır.

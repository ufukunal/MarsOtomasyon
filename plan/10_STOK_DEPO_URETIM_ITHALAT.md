# 10 — Stok, Depo, Üretim, Fason ve İthalat V4.1

## 1. Stok authority
`stock_movements` fiziksel stok source-of-truth'tur. Reservation movement değildir. Keyfi direct balance mutation yasaktır. Aynı fiziksel quantity aynı source lineage üzerinden yalnız bir kez etkilenir.

## 2. Ürün / stok kapsamı
Core ürün yetenekleri gerçek ihtiyaç oldukça:
- stoklu/stoksuz
- satılabilir
- satın alınabilir
- üretilebilir
- birim/barkod/QR arama
- tek satış + tek alış net fiyatı

Lot/seri V1 core kapsamında değildir.

## 3. Kullanılabilir stok / negatif stok
Temel:
`available = physical - reserved - quarantine_or_blocked`.

Kanal publish:
`publishable = available - channel_safety_stock`.

V1 negatif stok BLOCK'tur. Reservation `physical - blocked/quarantine` miktarını aşamaz. Backorder varsayılan değildir.

## 4. Stok maliyeti
V1 perpetual valuation **moving weighted average** kullanır. StockMovement gerektiğinde unit/total carrying cost taşır. Outbound current carrying value ile çıkar; transfer taşıdığı değeri korur. Silent zero-cost positive stock yoktur.

## 5. Depo / lokasyon / hareket
- ürün birden fazla depoda olabilir
- location opsiyonel alt kırılımdır
- hareket source belge/depo/lokasyon/miktar/cost lineage taşır
- technical effect/idempotency alanları normal UI'da gösterilmez

## 6. Depo transferi / transit custody
V16.3 ekranı cari/fiyat/KDV içermez.

Gerçek depolar arası akış:
`Taslak → Kaynak Çıkışı → Yolda → Kısmi/Tam Hedef Kabul → Tamamlandı`.

Kaynak issue sonrası hedef receipt'e kadar miktar ve carrying value şirket varlığı olarak **in-transit custody** altında izlenir. Bu ara aşama şirket inventory value'sunu sıfırlamaz.

Ekran:
- kaynak depo
- hedef depo
- ürün
- miktar
- açıklama/taşıma bilgisi
- progress

Source issue + destination receipt aynı transfer lineage'ında reconcile edilir. Aynı fiziksel hareket duplicate olmaz. Hedef kabul carrying value'yu aynen taşır; transfer P/L üretmez.

## 7. Stok sayımı
Sayım oturumu:
- depo
- tarih
- sayımı yapan
- ürün
- sistem miktarı
- sayılan miktar
- fark

Finalization exactly-once adjustment üretir. Positive adjustment mevcut güvenilir moving-average cost kullanır; cost yoksa explicit yetkili unit cost zorunludur. Quick Count barkod random scan ve sesli geri bildirim destekleyebilir.

## 8. Mal Kabul kontrolü / quantity split
GoodsReceiptLine fiziksel gelen miktarı gerekirse:
- `accepted_qty`
- `pending_quality_qty`
- `rejected_qty`
olarak böler.

`physical_received = accepted + pending + rejected`.

PurchaseOrder received progress yalnız accepted quantity ile kapanır. Pending/rejected quantity physically received custody'dedir ancak available değildir. Pending sonradan accepted/rejected olarak reclassify edilir; aynı quantity için ikinci physical stock IN yazılmaz.

Core kararlar:
- `Uygun`
- `Kontrol Bekliyor`
- `Uygun Değil`

Control Plan/CAPA/8D/SPC core değildir.

## 9. Ürün Teknik Bilgi Dosyası
Ürün Detail içinde readonly sunulur. Stabil ürün bilgisini taşır:
- teknik özellikler
- ölçüler/malzeme
- kullanım/dikkat bilgileri
- teknik görseller/dosyalar

Üretim operasyon talimatından ayrıdır.

## 10. Ürün Kurulum Kılavuzu / PDF Builder
Generic document/report designer değildir. Ürüne özel:
- adımlar
- uyarılar
- gerekli araçlar
- parçalar
- görseller
- A4 preview
ile kurulum kılavuzu üretebilir. Çıktı versioned artifact olarak saklanabilir.

## 11. Ürün görselleri
Görsel destination'ları dinamik olabilir:
- Ürün Kartı
- WooCommerce/site
- Trendyol ve diğer aktif marketplace'ler
- B2B
- belirli site/domain

Her destination bağımsız:
- main image
- gallery
- sort/order
- provider validation/result metadata

taşır.

Upload sonrası görsel editörü opsiyoneldir. Crop/rotate/flip/resize desteklenebilir. Mevcut görsel `Resmi Düzenle` ile açılabilir.

## 12. Basit Üretim
`Reçete → Üretim Emri → Malzeme Çıkışı → Mamul Girişi → Tamamla`.

Reçete hedef mamul ve gerekli malzeme/miktar/fire bilgisini taşır.

### Malzeme çıkışı
Hammadde/yarı mamul stock OUT ve carrying cost çıkışı.

### Mamul girişi
Üretilen mamul stock IN. Dağıtılabilir maliyet actual issued material + explicit production/subcontract cost'tan gelir.

### Tamamlama
- material issue reconcile
- output receipt reconcile
- fire/eksik explicit
- unresolved quantity yok

Routing, Work Center, OperationRun, ECO, OEE, APS/finite scheduling core değildir.

## 13. Teknik Üretim Dosyası
Ürün Teknik Bilgi Dosyasından ayrıdır. Reçete/üretim emri operasyonel bilgisi:
- nasıl yapılır
- dikkat noktaları
- malzeme/koli konumu
- fotoğraflar
- fason toplama talimatı

## 14. Fason / subcontract custody
`Gönderilen Malzeme → Gelen Mamul → Fire/Eksik → Kalan → Tamamla`.

Company-owned material subcontract custody'de quantity + carrying value ile izlenir. Fason firma cariyle ilişkilendirilebilir. Gönderim company inventory value'sunu yok etmez.

`gelen mamul + fire/eksik + remaining` reconcile edilmeden tamamlanmaz. Gelen mamul maliyetine gönderilen material carrying value + explicit fason hizmet/giderleri uygun basis ile aktarılabilir. Ayrı stok authority yoktur.

## 15. İthalat dosyası
Resmi gümrük/genel muhasebe platformu değildir; operasyon ve maliyet planlama aracıdır.

İçerik:
- shipment/konteyner
- ürünler
- koli/component eşleşmesi
- malzemenin hangi koli/konteynerde olduğu
- fotoğraf/teknik talimat
- üretim/toplama listesi
- fason gönderim listesi

## 16. Konteynerler arası eşleme
Aynı ürün birden fazla konteynerde bulunabilir. Genel gelen liste normalize edilir; container-product miktarları sipariş/plan ile reconcile edilir. Source lineage korunur.

## 17. İthalat → stok handoff
ImportShipment/Container lifecycle **stock authority değildir**.

Fiziksel ithal ürün kabulü:
- linked PurchaseOrder/GoodsReceipt üzerinden,
- veya satınalma siparişi yoksa controlled `ImportReceipt` application use-case'i üzerinden
`stock_movements` üretir.

Aynı container/product acceptance iki farklı handoff yolundan ikinci stock IN üretemez. Import module landed-cost/source lineage'ı receipt movement'a bağlar.

## 18. İthalat maliyeti
Maliyet konteyner ve shipment/genel seviyede tutulabilir.

Cost item örnekleri:
- ürün bedeli
- navlun/nakliye
- sigorta
- vergi/resmi gider
- aracı/hizmet gideri
- liman/depolama
- diğer yasal/operasyonel gider

Allocation basis:
- miktar
- ağırlık
- hacim
- ürün değeri
- manuel/özel oran

Dağıtılan toplam source toplam ile reconcile olur. Aynı cost item iki kez allocation üretemez.

## 19. Late cost
Geç gelen navlun/vergi/hizmet maliyeti original import/receipt lineage'a bağlanır. Current on-hand'a körlemesine yığılmaz; `21_MALIYETLENDIRME.md` ve A-17 posting policy'sine uyar.

## 20. Konteyner yükleme simülatörü
Planlama aracıdır; stock authority değildir.

Girdiler:
- konteyner iç ölçü/ağırlık sınırı
- koli ölçüleri
- koli/ürün ağırlığı
- yerleşim

Çıktılar:
- hacim doluluk
- ağırlık dağılımı/merkezi
- yerleşim senaryosu
- kullanıcı override/not

Simülasyon sonucu versioned snapshot olarak tutulabilir.

# 10 — Stok, Depo, Üretim, Fason ve İthalat V4

## 1. Stok authority
`stock_movements` fiziksel stok source-of-truth'tur. Reservation movement değildir. Keyfi direct balance mutation yasaktır. Aynı fiziksel quantity aynı source lineage üzerinden yalnız bir kez etkilenir.

## 2. Ürün / stok kapsamı
Core ürün yetenekleri gerçek ihtiyaç oldukça:
- stoklu/stoksuz
- satılabilir
- satın alınabilir
- üretilebilir
- birim/barkod/QR arama
- tek satış + tek alış fiyatı

Lot/seri V1 core kapsamında değildir.

## 3. Kullanılabilir stok
Temel:
`available = physical - reserved - quarantine_or_blocked`.

Kanal publish:
`publishable = available - channel_safety_stock`.

Quarantine/blocked yalnız Mal Kabul veya gerçek operasyon ihtiyacında kullanılır; generic QMS kurulmaz.

## 4. Depo / lokasyon / hareket
- ürün birden fazla depoda olabilir
- location opsiyonel alt kırılımdır
- hareket source belge/depo/lokasyon/miktar taşır
- technical effect/idempotency alanları normal UI'da gösterilmez

## 5. Depo transferi
V16.3 ekranı cari/fiyat/KDV içermez.

Gerçek depolar arası akış:
`Taslak → Kaynak Çıkışı → Yolda → Kısmi/Tam Hedef Kabul → Tamamlandı`.

Ekran:
- kaynak depo
- hedef depo
- ürün
- miktar
- açıklama/taşıma bilgisi
- progress

Source issue + destination receipt aynı transfer lineage'ında reconcile edilir. Aynı fiziksel hareket duplicate olmaz.

## 6. Stok sayımı
Sayım oturumu:
- depo
- tarih
- sayımı yapan
- ürün
- sistem miktarı
- sayılan miktar
- fark

Finalization exactly-once adjustment üretir. Quick Count barkod random scan ve sesli geri bildirim destekleyebilir.

## 7. Mal Kabul kontrolü
Satır:
- sipariş miktarı
- daha önce kabul
- bu kabul
- kalan
- `Uygun | Kontrol Bekliyor | Uygun Değil`

Kontrol bekleyen/uygun olmayan miktar gerekirse available stock dışında blocked/quarantine state'te tutulur. Control Plan/CAPA/8D/SPC core değildir.

## 8. Ürün Teknik Bilgi Dosyası
Ürün Detail içinde readonly sunulur. Stabil ürün bilgisini taşır:
- teknik özellikler
- ölçüler/malzeme
- kullanım/dikkat bilgileri
- teknik görseller/dosyalar

Üretim operasyon talimatından ayrıdır.

## 9. Ürün Kurulum Kılavuzu / PDF Builder
Generic document/report designer değildir. Ürüne özel:
- adımlar
- uyarılar
- gerekli araçlar
- parçalar
- görseller
- A4 preview
ile kurulum kılavuzu üretebilir. Çıktı versioned artifact olarak saklanabilir.

## 10. Ürün görselleri
Görsel destination'ları dinamik olabilir:
- Ürün Kartı
- Trendyol
- B2B
- belirli site/domain

Her destination bağımsız:
- main image
- gallery
- sort/order

taşır.

Upload sonrası görsel editörü opsiyoneldir. Crop/rotate/flip/resize desteklenebilir. Mevcut görsel `Resmi Düzenle` ile açılabilir.

## 11. Basit Üretim
`Reçete → Üretim Emri → Malzeme Çıkışı → Mamul Girişi → Tamamla`.

Reçete hedef mamul ve gerekli malzeme/miktar/fire bilgisini taşır.

### Malzeme çıkışı
Hammadde/yarı mamul stock OUT.

### Mamul girişi
Üretilen mamul stock IN.

### Tamamlama
- material issue reconcile
- output receipt reconcile
- fire/eksik explicit
- unresolved quantity yok

Routing, Work Center, OperationRun, ECO, OEE, APS/finite scheduling core değildir.

## 12. Teknik Üretim Dosyası
Ürün Teknik Bilgi Dosyasından ayrıdır. Reçete/üretim emri operasyonel bilgisi:
- nasıl yapılır
- dikkat noktaları
- malzeme/koli konumu
- fotoğraflar
- fason toplama talimatı

## 13. Fason
`Gönderilen Malzeme → Gelen Mamul → Fire/Eksik → Kalan → Tamamla`.

Company-owned material custody'de izlenir. Fason firma cariyle ilişkilendirilebilir. Gelen + fire/eksik + remaining reconcile edilmeden tamamlanmaz. Ayrı stok authority yoktur.

## 14. İthalat dosyası
Resmi gümrük/genel muhasebe platformu değildir; operasyon ve maliyet planlama aracıdır.

İçerik:
- shipment/konteyner
- ürünler
- koli/component eşleşmesi
- malzemenin hangi koli/konteynerde olduğu
- fotoğraf/teknik talimat
- üretim/toplama listesi
- fason gönderim listesi

## 15. Konteynerler arası eşleme
Aynı ürün birden fazla konteynerde bulunabilir. Genel gelen liste normalize edilir; container-product miktarları sipariş/plan ile reconcile edilir. Source lineage korunur.

## 16. İthalat maliyeti
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

## 17. Late cost
Geç gelen navlun/vergi/hizmet maliyeti original import/receipt lineage'a bağlanır. Current on-hand'a körlemesine yığılmaz; `21_MALIYETLENDIRME.md` policy'sine uyar.

## 18. Konteyner yükleme simülatörü
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

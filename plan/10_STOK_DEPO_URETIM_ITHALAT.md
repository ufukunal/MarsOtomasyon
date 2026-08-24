# 10 — Stok, Depo, Üretim ve İthalat

## Stok authority
`stock_movements`. Stok miktarı ledger hareketlerinden/projection'dan elde edilir; keyfi direct balance mutation yasaktır.

## Depo/Lokasyon
- bir ürün birden fazla depoda bulunabilir
- lokasyon opsiyonel alt kırılımdır
- transfer kaynak ve hedefi açıkça taşır
- rezervasyon fiziksel hareket değildir

## Depo transferi
V16.3 ekranı cari/fiyat/KDV içermez. Kaynak depo, hedef depo, ürün, miktar ve progress gösterilir.

Posting tek transaction'da kaynak OUT + hedef IN üretir.

## Stok sayımı
- sistem miktarı
- sayılan miktar
- fark

Onay/posting fark kadar adjustment movement üretir. Quick Count barkod tarama destekleyebilir.

## Üretim
Basit model:
`Reçete → Üretim Emri → Malzeme Çıkışı → Mamul Girişi → Tamamla`.

### Reçete
Mamul + gerekli malzeme satırları + standart miktar/fire bilgileri.

### Malzeme çıkışı
Hammadde/yarı mamul stock OUT.

### Mamul girişi
Üretilen mamul stock IN.

Tamamlama miktar uyumsuzluklarını doğrular.

## Fason
Aynı stok motoru kullanılır:
`Gönderilen Malzeme → Gelen Mamul → Fire/Eksik → Kalan → Tamamla`.

Fason firma cariyle ilişkilidir; malzeme mülkiyeti/konumu ayrı izlenebilir ancak ikinci bir stok authority kurulmaz.

## Teknik dosyalar
Ürün/üretim için teknik bilgi dosyası, kurulum kılavuzu, fotoğraf, dikkat edilecek hususlar ve üretim/toplama talimatları dosya sistemiyle ilişkilendirilir.

## İthalat ana modeli
- Shipment
- Container
- ContainerProduct
- Package/Box
- MaterialLocation
- ImportCost
- CostAllocation
- Compatibility/Instruction
- LoadingSimulation

## Konteynerler arası eşleme
Aynı ürün farklı konteynerlerde bulunabilir. Genel gelen liste normalize edilir; container-product miktarları toplam sipariş/planla uzlaştırılır.

## Koli/malzeme takibi
Ürün toplanırken veya fasona gönderilirken hangi malzemenin hangi koli/konteynerde olduğu listelenir. Fotoğraf ve montaj/not bilgisi çıktıda bulunabilir.

## İthalat maliyeti
Konteyner ve genel sevkiyat giderleri ayrı kalemlerdir. Vergi, nakliye, aracı/hizmet, liman, depolama, sigorta, resmi/operasyonel ek gider gibi yasal ve muhasebeleştirilebilir kategoriler tanımlanır.

Maliyet ürünlere miktar, hacim, ağırlık, değer veya özel dağıtım anahtarıyla dağıtılabilir. Kapanışta dağıtılan toplam kaynak giderle uzlaşır.

## Yükleme simülatörü
Konteyner iç ölçü/ağırlık sınırları, koli/ürün boyutları ve ağırlık merkezi/yayılımı kullanılarak yerleşim senaryosu oluşturulur. Simülasyon operasyon yardımcısıdır; stok authority değildir.

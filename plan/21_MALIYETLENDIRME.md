# 21 — Maliyetlendirme

## Amaç
MarsOtomasyon ürün, alış, üretim ve ithalat maliyetini izleyebilir; ancak V1'de tam genel muhasebe maliyet sistemi kurmaz.

## Ürün maliyeti
Temel maliyet kaynakları:
- alış fiyatı/mal kabul
- ithalat dağıtılmış maliyetleri
- üretimde tüketilen malzeme
- fason hizmet/giderleri
- tanımlı ek operasyonel giderler

Maliyet hesaplama yöntemi ayrı business policy olarak seçilir; stok miktarı ledger ile maliyet değerini aynı tabloda kontrolsüz karıştırmaz.

## İthalat maliyeti
Sevkiyat/konteyner giderleri ayrı kalemlerdir:
- ürün bedeli
- navlun/nakliye
- sigorta
- vergi/resmi gider
- liman/depolama
- aracı/hizmet gideri
- yasal olarak kaydedilen diğer operasyon giderleri

Dağıtım anahtarları:
- miktar
- ağırlık
- hacim
- ürün değeri
- özel oran

Her allocation run kaynak toplam, dağıtılan toplam ve farkı gösterir.

## Üretim maliyeti
En az:
- gerçek malzeme tüketimi
- fason gideri
- gerektiğinde tanımlı ek üretim gideri

V1'de karmaşık standart maliyet/MRP varyans platformu hedeflenmez.

## Kârlılık
Satış raporlarında gelir ile seçilen maliyet snapshot/projection karşılaştırılabilir. Geçmiş kârlılık raporu son master maliyet değişti diye sessizce yeniden anlam değiştirmemelidir.

## Döviz
Foreign currency maliyetinde işlem tarihindeki kur snapshot'ı saklanır. Sonraki kur değişikliği geçmiş allocation'ı değiştirmez; yeniden değerleme ayrı ihtiyaçtır.

## Rounding
Dağıtımda rounding artığı deterministik olarak bir/az sayıda satıra dağıtılır; toplam kaynak tutarla birebir uzlaşır.

## Audit
Maliyet allocation run'ı kim/ne zaman/hangi yöntemle yaptı bilgisiyle saklanır; kapatılmış run değiştirilecekse yeni version/reversal yaklaşımı kullanılır.

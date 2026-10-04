# Etiket, yazdırma ve print history

## PrintManager

Tüm yazdırma PrintManager üzerinden geçer.

Profil çözüm sırası:
1. company+user+machine+type
2. company+user+type
3. company+type
4. system default

## Ürün etiketi

Template izinli alanları:
- ürün kodu
- ürün adı
- barkod
- fiyat
- birim
- izinli kart alanları
- custom static text
- barcode/QR

## Koli etiketi

K-060/K-221:
- ambar/sevk kaynağına bağlıdır,
- koli etiketi bağımsız stok kaydı değildir,
- kaynak belge/order/shipment bilgileri template token'larından gelir.

## Ölçü/driver

- paper_code
- width_mm
- height_mm
- settings: dpi/gap/darkness vb.

ZPL/PDF/ileride Agent/Shell driver PrintManager arkasında kalır.

## Toplu baskı

Birden fazla ürün/belge/etiket tek batch job olabilir.
Her print job:
- actor
- profile
- template revision
- source
- quantity
- status
- result metadata

taşır.

Hassas belge payload'ı history'de kopyalanmaz.

# Fiyatlandırma — hangi fiyat nereden gelir

Belge satırına birim fiyatın nasıl belirlendiği tanımsızdı. Bu belge
o boşluğu doldurur.

## Çözümleme sırası

Kullanıcı ürünü seçtiğinde birim fiyat şu sırayla aranır:

```
1. Cariye atanmış fiyat listesinde, tarihi geçerli satır
2. Şirketin VARSAYILAN fiyat listesinde, tarihi geçerli satır
3. products.list_price
4. Bulunamazsa 0 — kullanıcı elle girer, uyarı gösterilir
```

İlk bulunan kazanır. Sonra **cari iskontosu** belgeye uygulanır
(satır fiyatına değil, belge iskontosu olarak — bkz. hesap sırası).

## Cari → fiyat listesi bağı

`contacts` tablosuna eklenir:

```php
$table->foreignId('price_list_id')->nullable()->constrained('price_lists');
```

Boşsa şirketin varsayılan listesi kullanılır.

## Tarih geçerliliği

`price_list_items.valid_from` / `valid_to` boşsa süresizdir.
Geçerlilik **belge tarihine** göre değerlendirilir, bugüne göre değil —
geriye dönük fatura kesilirken o günkü fiyat gelir.

Aynı ürün için çakışan tarih aralığı **engellenir** (G-110).

## Fiyat değiştirilebilir mi

Evet, kullanıcı satırda fiyatı değiştirebilir. Ama:

- Listeden gelen fiyattan **%20'den fazla sapma** varsa uyarı verilir
  (maliyet sapma uyarısıyla aynı mantık, eşik ayrı)
- Değiştirilen satır işaretlenir, `activity_log`'a düşer
- `prices.override` izni olmayan kullanıcı fiyatı **değiştiremez**
  (satış personeli için tipik kısıt)

## Maliyetin altında satış

Satır fiyatı ürünün hareketli ortalama maliyetinin altındaysa uyarı:
*"Bu satır maliyetin altında. Maliyet 2.513 ₺, fiyat 2.100 ₺."*

Uyarı yalnız `cost.view` izni olanda gösterilir — diğerlerinde
maliyet sızdırılmaz. İzni olmayan kullanıcı için uyarı metni
maliyetsizdir: *"Bu fiyat onay gerektirir."*

Engel değil, uyarıdır.

## Konfigüratörlü ürün

**Konfigüratör fiyatı etkilemez** (A-002). Yalnız ürün özelliklerini
tanımlar — gövde, kristal, duy gibi seçimler satıra bilgi olarak yazılır.
Fiyat normal çözümleme sırasından gelir. Seçimler sipariş satırında
**dondurulur**.

## Set ürün

Set fiyatı bileşenlerin toplamı **değildir**; setin kendi
`list_price` değeri kullanılır. Set bir satış birimidir ve genelde
bileşen toplamından ucuzdur.

## Alış tarafı

Alışta fiyat listesi kullanılmaz. Varsayılan, o tedarikçiden **son alış
fiyatıdır**; yoksa boş gelir. Girilen fiyat maliyet sapma uyarısına tabidir.

## Para birimi

Fiyat listesi kendi para biriminde tutulur. Belge para biriminden
farklıysa **belge tarihinin kuruyla** çevrilir ve çevrilmiş değer
satıra yazılır.

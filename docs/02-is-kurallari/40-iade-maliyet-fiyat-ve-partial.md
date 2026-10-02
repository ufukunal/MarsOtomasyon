# İade maliyet, fiyat ve kısmi işlem

## Kaynaklı satış iadesi maliyeti

K-103:

- kaynak satış stok çıkışının frozen `unit_cost` değeri kullanılır,
- return stock-in aynı unit_cost ile yazılır,
- karantinaya aynı unit_cost snapshot edilir,
- release ikinci stock movement veya moving-average update üretmez.

## Kaynaksız satış iadesi

K-110:

- stok maliyeti post anındaki current moving average,
- fiyat/KDV kullanıcı girişi,
- reason + ayrı izin + audit zorunlu.

## Alış iadesi maliyet seçimi

K-104, satır bazında:

- `current_moving_average`
- `source_purchase_cost`

seçeneklerinden biri.

Kaynaksız purchase_return yalnız `current_moving_average`.

Purchase return bir stok çıkışıdır; moving average değerini değiştirmez.

## Fiyat/KDV snapshot

K-107:

Kaynaklı return:

- unit_price
- satır/belge iskonto etkisi
- VAT rate
- unit
- conversion_factor/base_quantity

orijinal frozen kaynaktan alınır.

Güncel fiyat listesi veya ürün kartı kullanılmaz.

## Dövizli alış iadesi

K-108:

- source purchase_invoice frozen exchange_rate kullanılır,
- yeni kur alınmaz,
- kur farkı hesaplanmaz.

## Partial / multiple return

K-106:

Bir source line için:

```
remaining_returnable =
source_quantity
- effective_posted_return_quantity
```

Reverse edilmiş return miktarı effective toplamdan çıkar.

Aynı source line birden fazla return belge/satırına bölünebilir.

Transaction içinde source remaining yeniden okunur/kilitlenir.

## Önceki dönem

K-109:

- eski period mutate edilmez,
- cross-DB FK yok,
- return_sources frozen snapshot kullanılır,
- mevcut açık dönemde yeni return post edilir.

## Kaynaksız

K-110:

- contact + product + quantity zorunlu,
- fiyat/KDV manuel,
- reason zorunlu,
- ayrı izin + audit.

# Kanal stok ve fiyat senkronizasyonu

## Yön

K-192:

- product/content/stock/price: sistem → kanal
- order/cancel/return: kanal → sistem

Dış kanal ürün değişikliği internal product'u mutate etmez.

## Stock mode çözümü

K-173:

```
effective_mode =
listing.stock_mode ?? product.channel_stock_mode
```

## stock

K-174:

Yalnız listing'e bağlı ve satışa uygun location'lar toplanır.

```
available =
SUM(quantity - reserved - consignment_reserved - quarantine)
```

Subcontractor location dahil edilmez.

K-175:

```
after_withhold = max(0, available - withhold_quantity)
channel_qty =
max_channel_quantity null
    ? after_withhold
    : min(after_withhold, max_channel_quantity)
```

## production

K-176:

- fixed_quantity listing bazında,
- lead_time_days listing bazında,
- fiziksel stock quantity kullanılmaz.

Confirmed channel sales_order üretim-mode üründe Faz 8 kuralıyla draft production order oluşturabilir.

## manual

K-177:

- manual_quantity listing bazında,
- fiziksel stok değişimi manual quantity'yi otomatik değiştirmez.

## Set

K-178:

Set ürün stock modunda her component için yalnız listing location kapsamındaki available miktar kullanılır.

```
set_qty =
min(component_available / required_quantity)
```

## Fiyat

K-171/K-172:

- currency ilk sürüm TRY,
- listing price_override varsa kullanılır,
- yoksa mevcut fiyat çözümleme zinciri kullanılır.

## Tetikleme

K-057:

Stok değişince ilgili active listing'ler için anlık queue işi.

15 dakikalık tarama yedektir.

Aynı listing için bekleyen aynı amaçlı job coalesce edilir; iş çalışırken son gerçek miktar tekrar okunur.

## Hata

K-193:

- retry: 30 / 60 / 120 saniye,
- sonra persistent sync error,
- kullanıcı manuel retry yapabilir.

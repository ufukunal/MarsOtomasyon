# Pazaryeri stok gönderimi

## Karar (A-001)

**Satış anında anlık gönderim.** Stok değişince ilgili kanallara hemen
gönderilir; 15 dakikalık tarama yedek mekanizmadır.

## Akış

```
Stok hareketi yazıldı (satış, iade, sayım, transfer, üretim)
 → ürün hangi kanallarda yayında?
 → her kanal için ChannelStockSync işi kuyruğa atılır (gecikmesiz)
 → iş kanala yeni kullanılabilir miktarı gönderir
```

Kuyruk kullanılır, istek beklemez; kullanıcı faturayı kaydeder, gönderim
arka planda olur.

**Aynı ürün için bekleyen iş varsa yenisi eklenmez**, mevcut iş son
miktarı okur. 50 satırlık fatura 50 ayrı gönderim yapmaz.

## 15 dakikalık tarama — yedek

Anlık gönderim başarısız olursa (API hatası, kanal kapalı) 15 dakikalık
tarama farkı yakalar ve yeniden gönderir. İkisi birlikte çalışır.

## Üretimle karşılanan ürünler

Bazı ürünler stoktan değil **üretimden** karşılanır. Bu ürünlerde
kanala gönderilen miktar fiziksel stok değildir:

```php
$table->string('channel_stock_mode', 20)->default('stock');
// stock      → kullanılabilir stok gönderilir
// production → sabit miktar gönderilir (üretim kapasitesi)
// manual     → elle girilen miktar gönderilir
$table->decimal('channel_fixed_quantity', 18, 3)->nullable();
```

`production` modundaki ürün stok bitse de kanalda satışta kalır; sipariş
gelince üretim emri açılır. Teslim süresi kanal ürün ayarında belirtilir.

## Set ürün

Set stoğu `min(bileşen ÷ gerekli)`. Bir bileşen bitince **tüm
kanallarda 0** gönderilir.

## Ayrılan miktar

Kanal bazında isteğe bağlı: "pazaryerine en fazla şu kadar gönder" ya da
"son N adedi gönderme". Varsayılan kapalı.

## Hata yönetimi

Gönderim başarısızsa 3 kez denenir (30/60/120 sn). Hâlâ başarısızsa
`channel_sync_errors` tablosuna düşer ve ekranda kırmızı rozet çıkar.
Kullanıcı elle yeniden gönderebilir.

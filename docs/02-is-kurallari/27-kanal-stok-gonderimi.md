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

## Kanal stok modu

K-058 ürün üzerinde varsayılan `channel_stock_mode` değerini taşır:

- stock
- production
- manual

Faz 9 K-173 gereği channel listing bu modu override edebilir.

### stock

K-174 gereği tüm şirket stoğu otomatik toplanmaz. Yalnız ilgili listing'e açıkça bağlanan satışa uygun location'ların kullanılabilir stok toplamı kullanılır. Subcontractor location hariçtir.

K-175 gereği listing bazında:

- `withhold_quantity`
- `max_channel_quantity`

uygulanır.

### production

K-176 gereği `fixed_quantity` ve `lead_time_days` channel-product listing üzerindedir. Fiziksel stok miktarı gönderilmez.

### manual

K-177 gereği `manual_quantity` channel-product listing üzerindedir ve fiziksel stok değişiminden otomatik etkilenmez.

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

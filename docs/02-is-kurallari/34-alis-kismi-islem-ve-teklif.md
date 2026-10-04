# Alış kısmi işlem ve teklif seçimi

## Satınalma siparişi fulfillment

K-088 gereği purchase_order satırı kısmi teslim alınabilir.

```
ordered = source line.quantity
cancelled = source line.cancelled_quantity
received = order line'ı ancestry ile kaynak gösteren etkin posted goods_receipt line toplamı
direct_invoiced = ancestry order line'a ulaşan fakat zincirde goods_receipt bulunmayan etkin posted purchase_invoice line toplamı

kalan = ordered - cancelled - received - direct_invoiced
```

Aynı miktar hem doğrudan alış faturası hem goods_receipt üzerinden ikinci kez karşılanamaz.

## Goods receipt kalan miktarı

Goods receipt yalnız operasyonel fulfillment'tır; stok etkisi yoktur.

Bir order line'dan goods receipt oluştururken:

1. order line transaction içinde kilitlenir,
2. kalan yeniden hesaplanır,
3. seçilen miktar kalanı aşamaz,
4. child line `source_line_id = order line id` ile oluşur,
5. post edilen goods_receipt miktarı received toplamına girer.

## Kalanı iptal

`cancelled_quantity` kaynak purchase_order line üzerinde tutulur.

- quantity yerinde küçültülmez,
- cancelled amount yeniden goods_receipt veya direct purchase_invoice'a gidemez,
- fulfilled + cancelled = ordered olduğunda order kapatılabilir.

## Purchase invoice kaynağı

### Goods receipt üzerinden

Faturalanabilir miktar:

```
goods_receipt quantity
- etkin posted purchase_invoice child line toplamı
```

Bir goods_receipt birden fazla purchase_invoice'a bölünebilir.

### Birden fazla goods receipt birleştirme

Tek purchase_invoice'a alınabilecek receipt'ler:

- aynı supplier contact,
- aynı currency,
- uyumlu alış koşulları

taşımalıdır.

Miktarlar child line `source_line_id` ile kendi receipt satırını göstermeye devam eder.

### Doğrudan order → invoice

Order line'dan doğrudan purchase_invoice üretilebilir. Bu miktar `direct_invoiced` sayılır ve sonradan goods_receipt ile tekrar kullanılamaz.

### Doğrudan purchase invoice

Kaynak belge olmadan purchase_invoice oluşturulabilir. Source line null'dır; posting yine stok + cari + maliyet etkisi üretir.

## Lineage resolver

Tek helper parent zincirini geriye izler:

- cycle guard için visited line id seti,
- goods_receipt görüldüyse invoice receipt-source kabul edilir,
- purchase_order köküne ulaşılıp goods_receipt yoksa direct invoice fulfillment sayılır,
- purchase_request/supplier_quote ancestry bilgi amaçlı korunabilir; quantity fulfillment gerçekliği purchase_order/goods_receipt/purchase_invoice katmanında hesaplanır.

## Teklif seçimi

K-090/K-091:

- bir request'e birden fazla supplier_quote bağlanabilir,
- teklif karşılaştırması hem belge hem satır bazlı olabilir,
- otomatik winner/en ucuz seçim yok,
- ayrı approval state yok,
- seçim `purchasing.quote.select` iznine bağlı,
- seçim `purchase_quote_selections` ile saklanır,
- period audit yazılır.

## Belge bazlı seçim

Seçilen supplier_quote içindeki uygun satırlar request line bazında topluca seçilir.

Her request line için seçim kaydı yine ayrıdır. Böylece daha sonra hangi satırın hangi tekliften geldiği kaybolmaz.

## Satır bazlı seçim ve sipariş üretimi

Örnek:

- request line A → supplier X quote line
- request line B → supplier Y quote line
- request line C → supplier X quote line

Sipariş üretiminde:

- X için A+C satırlı purchase_order,
- Y için B satırlı purchase_order

oluşur.

Seçili quote line'dan order line'a `source_line_id` bağlanır; belge ilişkisinde `supplier_quote_to_order` yazılır.

## Yarış koşulları

- aynı request line için seçim update'i optimistic lock kullanır,
- sipariş üretirken ilgili selection + quote line'lar deterministik sırayla kilitlenir,
- aynı selection ikinci kez purchase_order'a dönüştürülemez,
- goods receipt ve invoice remaining hesapları source satırları kilit altında tekrar okuyarak yapılır.

## integrity:purchasing

Kontroller:

- quote selections doğru request/quote lineage,
- duplicate selection yok,
- selection→order supplier eşleşmesi,
- order cancelled + received + direct_invoiced <= ordered,
- goods_receipt invoiced toplamı receipt quantity'yi aşmıyor,
- invoice lineage cycle içermiyor,
- purchase_invoice stock/contact effect matrix'e uyuyor,
- goods_receipt yanlışlıkla stock/contact movement üretmemiş.

Fark raporlanır; otomatik düzeltme yok.

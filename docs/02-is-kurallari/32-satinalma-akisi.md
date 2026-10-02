# Satınalma akışı

## Kapsam

Faz 4 alış belge ailesi K-086 gereği esnektir:

```
satınalma talebi
  → tedarikçi teklifleri
  → satınalma siparişi
  → mal kabul / alış irsaliyesi
  → alış faturası
```

Ara adımlar zorunlu değildir. Kullanıcı iş ihtiyacına göre uygun bir aşamadan başlayabilir veya sonraki uygun belgeyi doğrudan oluşturabilir. Sistem kullanılmayan ara belgeleri yapay olarak üretmez.

## Belge tipleri

`documents.document_type` için Faz 4 tipleri:

- `purchase_request`
- `supplier_quote`
- `purchase_order`
- `goods_receipt`
- `purchase_invoice`

## Satınalma talebi

- Tedarikçi zorunlu değildir; `contact_id` null olabilir.
- Satırlar ürün, miktar, birim ve ihtiyaç notunu taşır.
- Draft düzenlenebilir.
- Confirm edildiğinde numara alır.
- Stok/cari/maliyet etkisi yoktur.
- Talep satırından supplier_quote satırı üretildiğinde `source_line_id` talep satırını gösterir.

## Tedarikçi teklifi

- `contact_id` zorunlu tedarikçidir.
- Bir purchase_request'ten birden fazla supplier_quote üretilebilir.
- Teklif fiyatı, para birimi, kur snapshot ve satır iskonto/KDV alanları ortak belge çekirdeğini kullanır.
- Stok/cari etkisi yoktur.
- Sistem otomatik en ucuz veya kazanan belirlemez.
- K-090 gereği belge veya satır bazlı seçim kullanıcı tarafından yapılır.
- K-091 gereği ayrı approval state yoktur; seçim `purchasing.quote.select` iznine bağlıdır.
- Seçimler `purchase_quote_selections` ile kalıcı tutulur ve period audit'e yazılır.

## Satınalma siparişi

- `contact_id` zorunlu tedarikçidir.
- Doğrudan oluşturulabilir veya teklif seçimlerinden üretilebilir.
- Teklif seçimlerinden üretimde satırlar supplier contact'a göre gruplanır; farklı tedarikçiler ayrı purchase_order alır.
- Sipariş stok/cari/maliyet etkisi üretmez.
- Kısmi teslim ve kalan iptal K-088'e göre desteklenir.

## Mal kabul / alış irsaliyesi

K-087 gereği operasyon kaydıdır.

- `contact_id` zorunlu tedarikçidir.
- Kaynak purchase_order olabilir veya doğrudan oluşturulabilir.
- Fiziksel teslim miktarı ve hedef location satırda tutulur.
- **Stok hareketi üretmez.**
- **Cari hareket üretmez.**
- **Maliyet güncellemez.**
- Posted mal kabul immutable'dır.
- Kısmi kabul source_line ilişkileriyle izlenir.

Bu karar gereği sistemde kullanılabilir stok, alış faturası post edilene kadar artmaz.

## Alış faturası

- `contact_id` zorunlu tedarikçidir.
- Doğrudan, purchase_order'dan veya goods_receipt'ten oluşturulabilir.
- Posting anında stok girişi + tedarikçi cari `credit` etkisi birlikte oluşur.
- Mal kabul daha önce stok yazmadığı için goods_receipt kaynaklı faturada stok ilk kez burada oluşur.
- Hareketli ortalama maliyet aynı posting transaction'ında güncellenir.
- Faz 4'te ödeme işlemi yoktur; K-089 gereği ödeme Faz 5'tedir.

## Belge ilişkileri

Faz 4 ilişki tipleri:

- `request_to_supplier_quote`
- `supplier_quote_to_order`
- `request_to_order`
- `order_to_goods_receipt`
- `order_to_purchase_invoice`
- `goods_receipt_to_purchase_invoice`

Miktar hesabının gerçek kaynağı yine `document_lines.source_line_id` ancestry zinciridir.

## Numara ve kesinlik

- Draft belge numarasız olabilir.
- Confirm/post geçişinde ilgili document_type serisinden numara üretilir.
- `GenerateDocumentNumber` transaction içinde `lockForUpdate` kullanır.
- Posted/kesinleşmiş belge yerinde değiştirilmez.

## Yetkiler

Asgari izinler:

- `purchasing.request.create`
- `purchasing.quote.create`
- `purchasing.quote.select`
- `purchasing.order.create`
- `purchasing.receipt.create`
- `purchasing.invoice.create`
- `purchasing.invoice.post`

Rol adı sabitlenmez; izin tabanlı kontrol yapılır.

## Audit

Period audit en az:

- talep confirm,
- teklif ekleme/değiştirme,
- teklif seçimi/değişikliği,
- sipariş confirm,
- kalan iptal,
- mal kabul post,
- alış faturası post/reverse,
- ±%25 maliyet sapma uyarısına rağmen devam

olaylarını kaydeder.

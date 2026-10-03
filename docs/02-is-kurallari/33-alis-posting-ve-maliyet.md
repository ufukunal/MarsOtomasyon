# Alış posting ve maliyet

## Etki matrisi

K-087/K-089:

| Tür | Stok | Cari | Maliyet | Kasa/Banka |
|---|---|---|---|---|
| purchase_request | yok | yok | yok | yok |
| supplier_quote | yok | yok | yok | yok |
| purchase_order | yok | yok | yok | yok |
| goods_receipt | yok | yok | yok | yok |
| purchase_invoice | **in** | **credit** | **moving average** | yok |

Faz 4'te ödeme posting'i yoktur.

## Purchase invoice posting

`PostPurchaseInvoice` belgeye özel input/permission/source doğrulamasını yapar ve ortak `PostDocument` çekirdeğine typed posting profile verir. Stok/cari etkilerini wrapper içinde ikinci kez yazmaz.

Transaction sırası:

1. idempotency doğrula,
2. `EnsurePeriodOpen(document_date)`,
3. gerekiyorsa numara üret,
4. ortak belge hesap motoruyla total doğrula,
5. source lineage ve kalan miktarı kilit altında doğrula,
6. her satır için temel birim miktarı doğrula,
7. alış maliyetini frozen kurla TRY'a çevir,
8. `RecordStockMovement(in, reason=purchase)`,
9. aynı ürün için `UpdateMovingAverage`,
10. belge toplamı kadar tedarikçi `contact_transactions.credit`,
11. status/posted actor snapshot,
12. post-write verify,
13. period audit,
14. idempotency done/result.

Hepsi tek period DB transaction içindedir.

## Döviz

K-013 gereği alışta döviz kullanılabilir.

- `documents.currency` belge para birimidir.
- `documents.exchange_rate` kayıt/posting için dondurulmuş kur snapshot'ıdır.
- TRY belgede exchange_rate = 1 olmalıdır.
- Dövizli alışta kur > 0 olmalıdır.
- Kur sonradan değişse bile posted belge/maliyet yeniden hesaplanmaz.
- Kur farkı hesabı Faz 4 kapsamında yoktur.

## Satır stok maliyeti

Satır net maliyet tabanı:

```
gross = quantity × unit_price
line_net = gross - line_discount_amount
allocated_document_discount = belge iskontosunun satıra oranlı payı
inventory_net = line_net - allocated_document_discount

inventory_net_try = inventory_net × frozen exchange_rate
unit_cost_try = inventory_net_try ÷ base_quantity
```

KDV stok maliyetine dahil edilmez.

Tüm aritmetik string + BCMath/Money ile yapılır; PHP float yoktur.

## Hareketli ortalama

`RecordStockMovement(in)` ve `UpdateMovingAverage` aynı transaction'dadır.

- unit_cost temel birim başına TRY maliyetidir,
- total_cost = base_quantity × unit_cost,
- ürün maliyet satırı kilitlenir,
- stok sıfır/negatifse yeni maliyet doğrudan incoming unit_cost olur,
- normal durumda K-006 hareketli ortalama formülü kullanılır,
- `last_purchase_price` ve `last_purchase_at` güncellenir.

## ±%25 sapma

K-007:

- referans = mevcut `product_costs.moving_average`,
- eşik = Master `companies.cost_deviation_threshold`, varsayılan 25,
- mutlak sapma eşik veya üstündeyse kullanıcıya uyarı,
- blok yok,
- devam edilirse period audit.

Referans 0 ise yüzde sapma hesaplanmaz.

## Cari hareket

Tedarikçiye borç, mevcut bakiye formülünde `credit` yönüdür:

```
bakiye = debit - credit
```

Alış faturası için:

- transaction_type = purchase_invoice,
- direction = credit,
- amount = `grand_total × frozen exchange_rate` ile şirket temel para birimi karşılığı,
- currency = şirket `base_currency` değeri (mevcut varsayılan TRY),
- due_date = belge due_date,
- document_id unique.

Ödeme Faz 5'te ters yönde borcu azaltacak akış olarak ele alınır; Faz 4 bunu üretmez.

## Reverse

Posted purchase_invoice yerinde değiştirilmez.

Reverse:

- yeni purchase_invoice reversal belgesi üretir,
- orijinal stok girişine karşı inverse stock out üretir,
- cari credit etkisine karşı debit hareket üretir,
- maliyet etkisi reversal sonrası stok/maliyet bütünlüğü kurallarına göre aynı transaction içinde doğrulanır,
- `reversal_of` ilişkisi kurulur,
- duplicate reverse engellenir.

Orijinal belge immutable kalır.

## Post-write verify

Commit öncesi:

- belge totals ortak hesap motoruyla eşleşmeli,
- her invoice line için tek beklenen purchase stock movement miktarı eşleşmeli,
- movement unit_cost frozen kur ve net maliyet hesabıyla eşleşmeli,
- product_cost moving_average güncellemesi beklenen sonuçla eşleşmeli,
- document_id ile tek supplier contact transaction bulunmalı,
- cari yön = credit ve ledger amount frozen kurla temel para birimi karşılığı olmalı,
- source remaining aşılmamalı.

Farkta transaction rollback.

## Kodlama öncesi blokaj — A-125

**[KARAR GEREKİYOR]** Mevcut etki matrisi tüm purchase_invoice satırlarını stock-in kabul eder. Faturalı navlun/fason hizmet satırının cari/VAT üretip stok/moving-average üretmemesi için A-125 kapanmalıdır. A-125 çözülmeden service purchase invoice posting davranışı uydurulmaz.

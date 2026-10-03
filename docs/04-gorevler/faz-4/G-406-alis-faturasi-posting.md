# G-406 — Alış faturası posting ve maliyet

## Amaç

Purchase_invoice posting'inde stok girişi, hareketli ortalama maliyet ve tedarikçi cari credit etkisini tek transaction içinde uygulamak.

## Önkoşul

G-401, G-404, G-405, G-303 PostDocument, G-203 maliyet altyapısı.

## Dokunulacak dosyalar

- purchase invoice create/detail bileşenleri
- `PostPurchaseInvoice`
- posting profile resolver genişletmesi
- alış maliyet hesap helper'ı
- `UpdateMovingAverage` düzeltmeleri
- purchase invoice feature testleri

## Şema / Kod

Posting profile:

| tür | hesap | stok | cari | maliyet | cash/bank |
|---|---|---|---|---|---|
| purchase_invoice stock line | line_calculated | in/purchase | credit (document total) | moving_average | yok |
| purchase_invoice service line | line_calculated | none | credit (document total) | none | yok |

Unit cost:

```
(line net - allocated document discount)
× frozen exchange_rate
÷ base_quantity
```

KDV maliyete girmez.

## Kurallar

- Tüm para hesabı string + BCMath/Money.
- TRY için kur 1; döviz kur snapshot'ı post sonrası değişmez.
- Cari ledger amount = grand_total × frozen exchange_rate; contact transaction currency = company base_currency.
- Wrapper stock/contact etkisini ikinci kez yazmaz; ortak posting transaction sahibini kullanır.
- Stok hareketi temel birimde.
- ±%25 sapma uyarı+audit, blok değil.
- Ödeme Action'ı yok; Faz 5.
- Direct invoice ve goods_receipt-source invoice stock satırlar için aynı posting etkisini üretir.
- K-257 service satırı stok/moving-average üretmez; cari/KDV/toplama dahildir.
- Aynı purchase_invoice stock + service satırlarını birlikte taşıyabilir.

## Kabul ölçütü

- Direct invoice stock satırı stock in + supplier credit üretiyor; supplier credit amount tüm belge grand_total'ının frozen kurla şirket temel para birimi karşılığıdır.
- Service-only purchase_invoice supplier credit/KDV/toplam üretir fakat stock movement ve moving-average üretmez.
- Mixed stock+service invoice yalnız stock satırlar için stock movement üretir; service tutarı cari belge toplamına dahildir.
- Receipt-source invoice ilk stok girişini invoice anında üretiyor.
- Goods receipt önceden stock yazmadığı için duplicate stock oluşmuyor.
- Moving average elle hesaplanmış beklenen değerle eşleşiyor.
- Dövizli faturada frozen kurdan TRY unit_cost doğru.
- VAT maliyete eklenmiyor.
- ±%25 uyarı bloklamıyor ve audit yazıyor.
- Aynı idempotency key ikinci stock/cari/maliyet etkisi üretmiyor.
- Post-write verify bozulduğunda transaction rollback.

## İstem

> purchase_invoice posting profilini ortak PostDocument zincirine ekle. K-257 line_kind ayrımını uygula: stock satır stock in + moving average, service satır stock etkisiz; supplier credit belge grand_total üzerinden tek transaction'da olsun. Float ve ikinci posting zinciri oluşturma.

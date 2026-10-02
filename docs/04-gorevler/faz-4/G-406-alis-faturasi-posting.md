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
| purchase_invoice | line_calculated | in/purchase | credit | moving_average | yok |

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
- Wrapper stock/contact etkisini ikinci kez yazmaz; ortak posting transaction sahibini kullanır.
- Stok hareketi temel birimde.
- ±%25 sapma uyarı+audit, blok değil.
- Ödeme Action'ı yok; Faz 5.
- Direct invoice ve goods_receipt-source invoice aynı posting etkisini üretir.

## Kabul ölçütü

- Direct invoice stock in + supplier credit üretiyor.
- Receipt-source invoice ilk stok girişini invoice anında üretiyor.
- Goods receipt önceden stock yazmadığı için duplicate stock oluşmuyor.
- Moving average elle hesaplanmış beklenen değerle eşleşiyor.
- Dövizli faturada frozen kurdan TRY unit_cost doğru.
- VAT maliyete eklenmiyor.
- ±%25 uyarı bloklamıyor ve audit yazıyor.
- Aynı idempotency key ikinci stock/cari/maliyet etkisi üretmiyor.
- Post-write verify bozulduğunda transaction rollback.

## İstem

> purchase_invoice posting profilini ortak PostDocument zincirine ekle. Stock in, supplier credit ve moving average tek transaction'da olsun; float ve ikinci posting zinciri oluşturma.

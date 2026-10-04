# Ekran — Mal Kabul / Alış İrsaliyesi Detayı

## Amaç

Fiziksel teslimi operasyonel olarak kaydetmek. K-087 gereği bu belge stok ve cari posting üretmez.

## Liste kolonları

- Mal Kabul No
- Tedarikçi
- Tarih
- Kaynak Sipariş
- Satır Sayısı
- Toplam Miktar
- Faturalama
- Durum

## Header

- tedarikçi
- document_date
- kaynak sipariş (opsiyonel)
- notes

## Satırlar

- ürün
- birim
- teslim miktarı
- hedef lokasyon
- kaynak sipariş miktarı
- daha önce teslim
- kalan
- faturalanan
- fatura kalan

## Eylemler

Taslak:

- Kaydet
- Post Et

Posted:

- Alış Faturası Oluştur
- Reverse

## Posting etkisi

Post edildiğinde:

- stok hareketi **yok**,
- cari hareket **yok**,
- maliyet güncellemesi **yok**.

Belge yalnız teslim/fulfillment kaydıdır.

## Kısmi işlem

- Sipariş kalanını aşan teslim reddedilir.
- Bir sipariş satırı birden fazla mal kabul satırına bölünebilir.
- Bir mal kabul satırı daha sonra birden fazla alış faturasına bölünebilir.

## Uyarı

Ekranda açıkça "Bu kayıt stoğu artırmaz; stok alış faturası post edildiğinde oluşur." bilgisi gösterilir.

# Ekran — Alış Faturası Detayı

## Liste kolonları

- Fatura No
- Tedarikçi
- Tarih
- Vade
- Para Birimi
- Kur
- Ara Toplam
- KDV
- Genel Toplam
- Durum

## Kaynaklar

Alış faturası:

- doğrudan,
- satınalma siparişinden,
- bir veya birden fazla mal kabulden

oluşturulabilir.

## Header

- tedarikçi
- document_date
- due_date
- currency
- exchange_rate
- notes

## Satırlar

- ürün
- lokasyon
- birim
- miktar
- conversion_factor/base_quantity
- unit_price
- satır iskontosu
- KDV
- net tutar
- kaynak satır

## Eylemler

Taslak:

- Kaydet
- Post Et

Posted:

- Reverse
- PDF

Faz 4'te **Ödeme** eylemi yoktur; K-089 gereği ödeme Faz 5'tedir.

## Posting etkisi

Post edildiğinde aynı transaction içinde:

- stock movement `in / purchase`,
- hareketli ortalama maliyet güncellemesi,
- tedarikçi `contact_transactions.credit`,
- period audit

oluşur.

## Döviz

- TRY dışı alış desteklenir.
- Kur belge üzerinde dondurulur.
- Posted belgede kur sonradan değiştirilmez.
- Kur farkı hesabı yoktur.

## Maliyet uyarısı

Mevcut moving average'a göre mutlak eşik aşımında açık uyarı gösterilir. Kullanıcı devam edebilir; işlem bloklanmaz ve audit yazılır.

## Kısmi fatura

- Bir mal kabul birden fazla faturaya bölünebilir.
- Aynı tedarikçi + currency + uyumlu alış koşullarındaki birden fazla mal kabul tek faturada birleşebilir.
- Kaynak miktar aşımı engellenir.

## Hesap

Ortak belge hesap motoru kullanılır. Ayrı alış hesap motoru oluşturulmaz.

# Ekran — Satınalma Siparişi Detayı

## Liste kolonları

- Sipariş No
- Tedarikçi
- Tarih
- Vade/Termin
- Para Birimi
- Toplam
- Teslim
- Fatura
- Kalan
- Durum

## Header

- tedarikçi
- document_date
- due_date / termin bilgisi
- currency
- exchange_rate
- notes

## Satırlar

- ürün
- birim
- miktar
- birim fiyat
- iskonto
- KDV
- net tutar
- iptal miktarı
- teslim alınan
- doğrudan faturalanan
- kalan

## Eylemler

Taslak:

- Kaydet
- Onayla

Onaylı:

- Mal Kabul Oluştur
- Alış Faturası Oluştur
- Kalanı İptal
- Kapat

## Kısmi akış

K-088 gereği:

- kısmi mal kabul yapılabilir,
- kalan açık kalabilir,
- kullanıcı kalan miktarı iptal edebilir,
- doğrudan alış faturası sipariş kalanını kullanabilir.

Gösterilen delivered/invoiced/kalan değerleri child line toplamlarından hesaplanır; ayrı ikinci gerçek kolon değildir.

## Etki

Satınalma siparişi stok, cari veya maliyet hareketi üretmez.

## Kurallar

- Tedarikçi zorunlu.
- Confirm sonrası immutable.
- cancelled_quantity kaynak satır quantity'sini değiştirmez.
- İptal edilen miktar tekrar mal kabul veya faturaya gidemez.

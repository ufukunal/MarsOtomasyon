# Ekran — Alış İadesi

## Amaç

Tedarikçiye ürün iadesini stok out + supplier debit olarak kaydetmek.

## Kaynak modu

- Aynı dönem alış faturası
- Önceki dönem alış faturası
- Kaynaksız manuel

## Header

- Tedarikçi
- document_date
- kaynak belge
- reason_code
- açıklama

## Satırlar

- ürün
- lokasyon
- birim
- miktar
- frozen fiyat/iskonto/KDV
- maliyet temeli
- unit_cost

## Maliyet temeli

Kaynaklı satır:

- Mevcut Moving Average
- Kaynak Alış Maliyeti

Kaynaksız satır:

- yalnız Mevcut Moving Average

## Döviz

Kaynak dövizli alış faturası varsa original frozen kur kullanılır. Güncel kur seçimi yoktur.

## Posting

- stock out
- supplier contact debit
- cash/bank hareketi yok

## Eylemler

- Kaydet
- Post Et
- Reverse

Ayrı approval state yoktur.

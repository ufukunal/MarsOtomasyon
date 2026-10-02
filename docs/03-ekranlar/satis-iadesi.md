# Ekran — Satış İadesi

## Amaç

Müşteriden dönen ürünü cari credit + fiziksel stok girişi + karantina olarak kaydetmek.

## Kaynak modu

- Aynı dönem fatura satırından
- Önceki dönem fatura satırından
- Kaynaksız manuel

## Header

- Cari
- document_date
- kaynak belge bilgisi — varsa
- reason_code
- açıklama

## Satırlar

- ürün
- lokasyon
- birim
- miktar
- fiyat
- iskonto
- KDV
- base_quantity
- unit_cost bilgisi

Kaynaklı satırlar frozen kaynak değerlerden gelir.

## Kaynaksız

- cari + ürün + miktar zorunlu
- fiyat/KDV manuel
- reason zorunlu
- ayrı yetki gerekir
- maliyet = current moving average

## Posting özeti

- customer credit
- stock in
- quarantine +Q
- kullanılabilir stok artmaz

## Eylemler

- Kaydet
- Post Et
- Reverse
- Karantina Kaydını Aç

Ayrı approval state yoktur.

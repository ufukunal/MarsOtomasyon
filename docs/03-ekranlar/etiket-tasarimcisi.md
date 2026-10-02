# Ekran — Etiket Tasarımcısı

## Amaç
Ürün ve koli etiketlerini cihazdan bağımsız template olarak tanımlamak.

## Boyut
- paper_code
- width_mm
- height_mm
- render type: zpl | pdf

## Alanlar
- ürün kodu/adı
- barkod
- fiyat
- birim
- static text
- QR
- koli/sevk kaynak alanları

## Kurallar
- Marka/model özel mantık template'e gömülmez.
- DPI/gap/darkness print profile settings'tedir.
- Koli etiketi ambar/sevk kaynağı olmadan basılmaz.
- Serbest kod yoktur.

## Önizleme
Gerçek veya örnek DTO ile preview.

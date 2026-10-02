# Ekran — Reçeteler

## Liste

- Mamul
- Reçete No
- Revizyon
- Output Quantity
- Aktif/Pasif
- Son Güncelleme

## Detay

- Mamul
- Output Quantity
- Revizyon
- Component satırları:
  - ürün
  - birim
  - miktar
  - base_quantity
  - conversion_factor

## Eylemler

- Yeni Reçete
- Yeni Revizyon
- Aktif Yap / Pasifleştir
- Üretim Emri Aç

## Kurallar

- Bir mamulde tek aktif reçete.
- Revizyon immutable.
- Fire yüzdesi alanı yok.
- Reçete component miktarları output_quantity tabanına göre ölçeklenir.

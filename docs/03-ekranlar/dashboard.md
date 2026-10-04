# Ekran — Dashboard

## Amaç
Güncel operasyonu rapor query servislerinden türetilen özet kart ve grafiklerle göstermek.

## Kartlar
Yetkiye göre:
- günlük/aylık satış
- açık sipariş
- stok kritik ürün
- cari alacak/borç
- gecikmiş alacak
- kasa/banka
- çek/senet vade
- karantina
- açık satınalma
- production orders
- kanal sync hataları

## Kurallar
- Dashboard ayrı bakiye tablosu değildir.
- Her kart kaynak rapor/query servisine bağlıdır.
- cost.view yoksa maliyet/kâr kartı yoktur.
- anlık stok/cari cache edilmez.
- grafik drill-down rapora gider.

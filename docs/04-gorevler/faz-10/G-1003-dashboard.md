# G-1003 — Dashboard

## Amaç
Operasyonel özet kart/grafikleri mevcut rapor query servislerinden üretmek.

## Önkoşul
G-1001, G-1002.

## Dokunulacak dosyalar
- Dashboard Livewire
- card/widget definitions
- drill-down links
- tests

## Şema / Kod
Ayrı business balance tablosu yok.

## Kurallar
- Anlık stok/cari cache yok.
- cost.view yoksa maliyet/kâr kartı yok.
- Kart drill-down ilgili rapora gider.

## Kabul ölçütü
- Kartlar rapor query sonucu ile eşleşiyor.
- Yetki kart görünürlüğünü etkiliyor.
- Drill-down filtreleri doğru taşıyor.

## İstem
> Dashboard'u ayrı veri kaynağı yaratmadan rapor servislerinden besle.

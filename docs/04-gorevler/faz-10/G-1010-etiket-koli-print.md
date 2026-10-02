# G-1010 — Ürün etiketi, koli etiketi ve PrintManager

## Amaç
Ürün/koli etiket template'lerini ölçü/driver bağımsız PrintManager akışına bağlamak.

## Önkoşul
G-1008/G-1009, G-010.

## Dokunulacak dosyalar
- label designer
- ZPL/PDF renderers
- product/carton label DTO
- PrintManager integration
- tests

## Şema / Kod
paper_code + width_mm + height_mm.

## Kurallar
- Printer marka/model bağımsız.
- Koli etiketi ambar/sevk kaynağına bağlı.
- ZPL driver abstraction arkasında.
- SVG yok.

## Kabul ölçütü
- Ürün etiketi PDF/ZPL render oluyor.
- Koli etiketi kaynaksız basılamıyor.
- Ölçü print profile ile çözülüyor.
- Direct printer access yok.

## İstem
> K-219…K-222 ürün/koli etiket sistemini PrintManager üzerinden uygula.

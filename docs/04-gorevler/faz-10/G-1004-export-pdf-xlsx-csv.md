# G-1004 — PDF / XLSX / CSV export

## Amaç
Ekran raporuyla aynı query semantiğinden PDF, XLSX ve CSV üretmek.

## Önkoşul
G-1001.

## Dokunulacak dosyalar
- exporters
- Browsershot report renderer
- spreadsheet/csv exporter
- export tests

## Şema / Kod
Kanonik kaynak: iş kuralı 54.

## Kurallar
- PDF Browsershot.
- XLSX/CSV aynı dataset.
- Numeric hücre numeric.
- Money hesabı float değil.
- Permission/export dataset aynı.

## Kabul ölçütü
- Ekran ve export toplamları aynı.
- XLSX numeric hücreler sayı.
- Cost yetkisi exportta da korunuyor.
- PDF pagination/header/footer çalışıyor.

## İstem
> Ortak rapor sonucundan PDF/XLSX/CSV export katmanını uygula.

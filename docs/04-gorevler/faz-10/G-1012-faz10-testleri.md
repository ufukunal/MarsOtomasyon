# G-1012 — Faz 10 bütünleşik testler

## Amaç
Rapor, export, çok dönem, dashboard, template, etiket ve print akışlarını gerçek PostgreSQL üzerinde bütünleşik doğrulamak.

## Önkoşul
G-1001…G-1011.

## Dokunulacak dosyalar
- Faz10ReportingTest
- Faz10MultiPeriodTest
- Faz10ExportTest
- Faz10TemplatePrintTest
- security/permission tests

## Şema / Kod
Yeni production şeması yok.

## Kurallar
- Master + birden fazla period DB.
- Gerçek PostgreSQL.
- Browsershot PDF entegrasyonu test seam ile.
- cost.view/reports.consolidated mutlaka test edilir.
- Genel muhasebe kapsamı eklenmez.

## Kabul ölçütü
- Ekran/PDF/XLSX/CSV toplamları tutarlı.
- Multi-period context güvenli.
- Cost alanı yetkisiz kullanıcıya SQL/select seviyesinde yok.
- Template arbitrary code çalıştırmıyor.
- Print profile resolution doğru.
- Product/carton labels doğru.
- Queue export doğru period context.
- Pint/Larastan/Pest yeşil.

## İstem
> Faz 10 için gerçek PostgreSQL multi-db, permission, export ve template/print bütünleşik testlerini yaz.

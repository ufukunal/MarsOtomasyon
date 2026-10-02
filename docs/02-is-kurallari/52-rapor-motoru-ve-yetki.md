# Rapor motoru ve yetki

## Tek motor

Her rapor ReportDefinition + ReportQuery sözleşmesi kullanır.

Rapor:
- filtreleri doğrular,
- yetkiyi sorgudan önce kontrol eder,
- kanonik tablolardan okur,
- kolon/total metadata üretir,
- ekran/export aynı query semantiğini kullanır.

## Yetki

- temel rapor permission'ı rapor bazlıdır,
- cost.view yoksa maliyet, brüt kâr, marj alanları SELECT'e alınmaz,
- reports.consolidated yoksa çok dönem/company konsolidasyonu açılamaz,
- drill-down hedef belgenin/kartın normal permission kontrolüne tabidir.

## Tarih

created_at iş tarihi değildir.

Kaynağa göre:
- document_date
- transaction_date
- movement_date
- completion_date
- adjustment_date

kullanılır.

## Cache

Stok/cari anlık bakiye cache edilmez.

Ağır tarihsel immutable rapor cache edilirse anahtar:
company + periods + report + filters + permission scope + definition version.

## Dashboard

Dashboard ayrı bakiye kaynağı değildir. Aynı report/query servislerini kullanır.

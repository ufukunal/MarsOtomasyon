# G-607 — Önceki dönem iadesi

## Amaç

Kapalı/eski dönem faturasını mevcut açık dönemde güvenli şekilde iade edebilmek.

## Önkoşul

G-602, PeriodContext altyapısı.

## Dokunulacak dosyalar

- prior-period return source query
- period_source lifecycle
- snapshot builder
- cross-period tests

## Şema / Kod

Eski source:

- read-only
- scalar period/document/line ids
- frozen business snapshot

Yeni return:

- current period documents/document_lines
- eski DB'ye FK yok
- eski DB'ye write yok

## Kurallar

- Kullanıcının eski period erişimi doğrulanır.
- Source posted olmalı.
- Return mevcut period document_date ile period lock'a tabi.
- Source snapshot sonradan eski DB değişse bile return geçmişini korur.

## Kabul ölçütü

- 2025 source 2026 return oluşuyor.
- 2025 DB write almıyor.
- Cross-DB FK yok.
- Snapshot fiyat/KDV/cost/FX doğru.
- Yetkisiz eski period okunamıyor.

## İstem

> K-109 cross-period iade modelini read-only source + current-period snapshot olarak uygula.

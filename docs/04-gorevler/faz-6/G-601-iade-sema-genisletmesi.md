# G-601 — Faz 6 iade şema genişletmesi

## Amaç

Sales_return/purchase_return belge tiplerini, return source snapshot ve quarantine gerçek kaynağını eklemek.

## Önkoşul

G-301, Faz 2 stok çekirdeği, K-098…K-113.

## Dokunulacak dosyalar

- DocumentType genişletmesi
- DocumentRelationType genişletmesi
- return_sources migration/model
- quarantine_entries migration/model
- schema feature testleri

## Şema / Kod

Yeni document type:

- sales_return
- purchase_return

Yeni relation type:

- return_source
- return_reversal_of

Veri modeli 39 uygulanır.

## Kurallar

- Cross-period source için gerçek FK yok.
- Aynı-period source_line_id korunur.
- Quarantine gerçek kaynağı quarantine_entries'tır.
- reason code sabit K-113 listesinden.
- Period tablolarda company_id yok.

## Kabul ölçütü

- PostgreSQL migration up/down.
- Return source same/prior/manual modları çalışıyor.
- Quarantine CHECK kuralları çalışıyor.
- reason code dışı değer reddediliyor.
- cross-DB FK oluşmuyor.

## İstem

> Faz 6 iade şemasını veri modeli 39'a göre uygula; aynı-period ve cross-period source semantiğini karıştırma.

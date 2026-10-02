# G-600 — Faz 6 İade özeti

## Amaç

Faz 6 satış ve alış iadelerini, kaynaklı/kaynaksız/cross-period senaryolarını, karantina ve maliyet etkilerini mevcut belge-stok-cari-finans çekirdeği üzerinde tamamlar.

## Kilit kararlar

- K-098…K-113.

## Veri modeli

- 39-return_sources-and-quarantine.md
- mevcut documents/document_lines/document_relations
- mevcut stock_movements/stock_balances/contact_transactions

## İş kuralları

- 39-iade-akisi.md
- 40-iade-maliyet-fiyat-ve-partial.md
- 41-karantina-kontrolu.md

## Ekranlar

- satis-iadesi.md
- alis-iadesi.md
- karantina-kontrolu.md
- iade-kaynak-secimi.md

## Etki matrisi

| Belge | Stok | Cari | Karantina | Finans |
|---|---|---|---|---|
| sales_return | in | customer credit | +Q | yok |
| purchase_return | out | supplier debit | yok | yok |

## Görev sırası

| Görev | İçerik |
|---|---|
| G-601 | iade şema/type/relation genişletmesi |
| G-602 | iade kaynak çözümleme |
| G-603 | satış iadesi posting |
| G-604 | alış iadesi posting |
| G-605 | kısmi/çoklu iade |
| G-606 | karantina kontrolü |
| G-607 | cross-period iade |
| G-608 | reverse/integrity |
| G-609 | gerçek PostgreSQL Faz 6 testleri |

## Faz bitiş ölçütü

Dokümantasyon seti yazılmıştır. Kodlama/uygulama G-601…G-609 kabul ölçütleri gerçek PostgreSQL üzerinde geçmeden tamamlanmış sayılmaz.

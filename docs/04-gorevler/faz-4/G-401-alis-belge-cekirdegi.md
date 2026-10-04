# G-401 — Faz 4 alış belge çekirdeği

## Amaç

Faz 3'te kurulan ortak ticari belge çekirdeğini Faz 4 belge tipleri ve teklif seçimleriyle genişletmek; aynı şemayı ikinci kez kurmamak.

## Önkoşul

G-301, G-302, G-303 tamamlanmış olmalı. K-086…K-091 kilitlidir.

## Dokunulacak dosyalar

- mevcut `DocumentType` enum/sözleşmesi
- mevcut `DocumentRelationType` enum/sözleşmesi
- period migration: `purchase_quote_selections`
- `PurchaseQuoteSelection` period modeli
- `tests/Feature/Purchasing/PurchaseSchemaTest.php`

## Şema / Kod

Yeni document type değerleri:

- purchase_request
- supplier_quote
- purchase_order
- goods_receipt
- purchase_invoice

Yeni relation type değerleri:

- request_to_supplier_quote
- supplier_quote_to_order
- request_to_order
- order_to_goods_receipt
- order_to_purchase_invoice
- goods_receipt_to_purchase_invoice

Yeni tablo yalnız `docs/01-veri-modeli/35-purchase_quote_selections.md` şemasına göre oluşturulur.

Mevcut documents/document_lines/contact_transactions tablolarına yalnız yeni belge tipi için gereksiz kolon eklenmez.

## Kurallar

- Period tablolarında `company_id` yok.
- Actor user alanlarına cross-DB FK yok.
- Belge bazlı teklif seçimi de satır bazlı seçim tablosunu kullanır.
- Bir request line için en fazla bir seçim vardır.
- Selection tablo fiyat/miktar kopyası tutmaz.

## Kabul ölçütü

- Migration gerçek PostgreSQL'de up/down çalışıyor.
- Yeni document/relation type değerleri kabul ediliyor.
- purchase_quote_selections FK/unique kuralları çalışıyor.
- Yanlış document type satırlarını seçim Action'ı reddediyor.
- Aynı request_line ikinci kez seçilemiyor.
- Period tabloya company_id veya Master user FK eklenmemiş.

## İstem

> Ortak Faz 3 belge tablolarını tekrar kurmadan Faz 4 type/relation genişletmelerini ve purchase_quote_selections tablosunu uygula. Veri modeli 35 ve K-086…K-091 dışına çıkma.

# G-301 — documents, document_lines ve document_relations

## Amaç

Genel belge başlık/satır altyapısını period DB'de kurmak. Satış ve ileride alış belgeleri aynı tablo ailesini kullanacak.

## Önkoşul

G-006, G-007, G-018, Faz 1 kartları, G-102 birim dönüşümleri.

## Dokunulacak dosyalar

- `database/migrations/period/*_create_documents_table.php`
- `database/migrations/period/*_create_document_lines_table.php`
- `database/migrations/period/*_create_document_relations_table.php`
- `app/Models/Period/Document.php`
- `app/Models/Period/DocumentLine.php`
- `app/Models/Period/DocumentRelation.php`
- `app/Enums/DocumentType.php`
- `tests/Feature/Documents/DocumentSchemaTest.php`

## Şema / Kod

Şema kaynağı:

- `docs/01-veri-modeli/30-documents.md`
- `31-document_lines.md`
- `33-document_relations.md`

### documents zorunlu alanları

```
id
document_type
number nullable
revision_no
document_date
due_date nullable
valid_until nullable
contact_id nullable -> contacts
currency
exchange_rate
status
discount_rate
discount_amount
subtotal
tax_base
vat_amount
rounding_difference
grand_total
requirements_snapshot jsonb nullable
notes nullable
version
created_by + created_by_name
posted_by + posted_by_name
posted_at
timestamps
```

`company_id` ekleme.

Unique: `document_type + number + revision_no`.

### document_lines alanları

```
id
document_id
line_no
line_kind = stock|service
product_id nullable (stock satırda zorunlu)
description
unit_id nullable (stock satırda zorunlu)
quantity
conversion_factor nullable (stock satırda zorunlu)
base_quantity nullable (stock satırda zorunlu)
location_id nullable (service satırda null)
unit_price
line_discount_rate
line_discount_amount
vat_rate
line_total
reserve_stock
cancelled_quantity
configuration jsonb nullable
source_line_id nullable
version
timestamps
```

K-257: `line_kind=stock` satırda `base_quantity = quantity × conversion_factor` posting öncesi doğrulanır. `line_kind=service` satırda product/unit/conversion/base_quantity/location stok alanları nullable/null olabilir ve stok etkisi üretmez.

### document_relations

```
source_document_id
target_document_id
relation_type
created_by
created_by_name
timestamps
```

Başlangıç ilişki tipleri: revision_of, quote_to_order, order_to_dispatch, order_to_invoice, dispatch_to_invoice, quote_to_proforma, order_to_proforma, proforma_to_invoice, collection_source, reversal_of.

## Kurallar

- Modeller `PeriodModel`.
- Aynı period kart ilişkileri gerçek FK.
- Master user actor alanlarına FK yok.
- Taslak belge numarasız olabilir.
- Posted belge cascade ile yanlışlıkla silinmemeli; uygulama katmanı immutable kuralı uygular.
- Document silme yalnız draft durumunda Action üzerinden yapılır.
- Satır `source_line_id` partial işlem zincirinin miktar kaynağıdır.
- Sevk/fatura toplamlarını ayrı denormalize kolon olarak ekleme.
- `version` optimistic lock kullanılır.

## Kabul ölçütü

- Migration gerçek PostgreSQL'de up/down çalışıyor.
- Period tablolarda company_id yok.
- documents.contact_id gerçek period FK'dir. Stock line'larda product_id/unit_id/location_id varsa gerçek period FK kullanılır; K-257 service line nullable stok bağları FK zorunluluğu yaratmaz.
- Actor alanlarında cross-DB user FK yok.
- Aynı document_type/number/revision_no tekrar edemiyor.
- line_kind CHECK'i geçerli; stock satırda quantity/conversion/base_quantity zorunlulukları, service satırda nullable stok alanları ve quantity/discount CHECK kısıtları geçerli.
- document_relations source != target CHECK geçerli.
- Aynı target document için ikinci `reversal_of` DB partial unique index ile reddediliyor.
- Draft silinebilir; posted silme Action seviyesinde reddedilir.
- Model relation'ları doğru connection'da çalışıyor.

## İstem

> G-301'i veri modeli 30, 31 ve 33'e göre uygula. K-257 `line_kind=stock|service` ayrımını ve service satır nullable stok alanlarını atlama. Period tablolarına company_id veya Master user FK ekleme. source_line_id ve revision_no'yu koru. Schema testini gerçek PostgreSQL ile yaz.

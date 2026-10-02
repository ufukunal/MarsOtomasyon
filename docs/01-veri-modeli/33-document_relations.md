# document_relations

**Veritabanı: DÖNEM**

Belgeler arası dönüşüm/bağlantıyı tutar. Miktar hesabının kendisi satır düzeyinde `document_lines.source_line_id` üzerinden yapılır.

## Şema

```php
Schema::connection('period')->create('document_relations', function (Blueprint $table) {
    $table->id();

    $table->foreignId('source_document_id')
        ->constrained('documents')
        ->restrictOnDelete();

    $table->foreignId('target_document_id')
        ->constrained('documents')
        ->restrictOnDelete();

    $table->string('relation_type', 40);

    $table->unsignedBigInteger('created_by')->nullable();
    $table->string('created_by_name')->nullable();
    $table->timestamps();

    $table->unique(
        ['source_document_id','target_document_id','relation_type'],
        'document_relations_unique'
    );
});
```

## Faz 3 ilişki tipleri

- `revision_of`
- `quote_to_order`
- `order_to_dispatch`
- `order_to_invoice`
- `dispatch_to_invoice`
- `quote_to_proforma`
- `order_to_proforma`
- `proforma_to_invoice`
- `collection_source`
- `reversal_of`

## Faz 4 ilişki tipleri

- `request_to_supplier_quote`
- `supplier_quote_to_order`
- `request_to_order`
- `order_to_goods_receipt`
- `order_to_purchase_invoice`
- `goods_receipt_to_purchase_invoice`

Liste uygulama enum'u ile yönetilir; sonraki fazlar yeni ilişki tipi ekleyebilir.

PostgreSQL bütünlük indeksleri:

```php
DB::connection('period')->statement(<<<'SQL'
ALTER TABLE document_relations
ADD CONSTRAINT document_relations_source_target_different
CHECK (source_document_id <> target_document_id)
SQL);

DB::connection('period')->statement(<<<'SQL'
CREATE UNIQUE INDEX document_relations_one_reversal_per_target
ON document_relations (target_document_id)
WHERE relation_type = 'reversal_of'
SQL);
```

Böylece aynı orijinal belge için ikinci `reversal_of` kaydı DB seviyesinde de engellenir.

## İlişki yönü

- Dönüşüm: `source_document_id = kaynak`, `target_document_id = üretilen belge`.
- `revision_of`: source = yeni revizyon, target = önceki revizyon.
- `reversal_of`: source = reversal belge, target = orijinal belge.
- `collection_source`: source = tahsilat, target = bilgi amaçlı kaynak fatura.

## Kurallar

- Kaynak ve hedef aynı belge olamaz; bu kural DB CHECK ile de korunur.
- Posted belge silinmediği için ilişki zinciri tarihsel olarak korunur.
- Teklif revizyonunda yeni belge eski revizyona `revision_of` ile bağlanır; aynı `number`, artan `revision_no` kullanılır.
- Aynı carinin uyumlu birden fazla irsaliyesi tek faturaya bağlanabilir.
- Bir irsaliye birden fazla faturaya bağlanabilir; gerçek kısmi miktar child satırların `source_line_id` toplamından hesaplanır.
- İrsaliyeden satış faturası oluştuğunda ilişki stok hareketinin ikinci kez yazılmamasını belirleyen kaynak kanıtlarından biridir.
- Faz 4'te goods_receipt stok etkisizdir; goods_receipt→purchase_invoice ilişkisi stok tekrarını önlemek için değil kısmi faturalama/lineage kanıtı olarak kullanılır.
- Faz 4 miktar gerçekliği de document_lines.source_line_id üzerinden hesaplanır; document_relations miktar kolonu taşımaz.

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
- `proforma_to_invoice`
- `collection_source`
- `reversal_of`

Liste uygulama enum'u ile yönetilir; sonraki fazlar yeni ilişki tipi ekleyebilir.

## İlişki yönü

- Dönüşüm: `source_document_id = kaynak`, `target_document_id = üretilen belge`.
- `revision_of`: source = yeni revizyon, target = önceki revizyon.
- `reversal_of`: source = reversal belge, target = orijinal belge.
- `collection_source`: source = tahsilat, target = bilgi amaçlı kaynak fatura.

## Kurallar

- Kaynak ve hedef aynı belge olamaz.
- Posted belge silinmediği için ilişki zinciri tarihsel olarak korunur.
- Teklif revizyonunda yeni belge eski revizyona `revision_of` ile bağlanır; aynı `number`, artan `revision_no` kullanılır.
- Aynı carinin uyumlu birden fazla irsaliyesi tek faturaya bağlanabilir.
- Bir irsaliye birden fazla faturaya bağlanabilir; gerçek kısmi miktar child satırların `source_line_id` toplamından hesaplanır.
- İrsaliyeden fatura oluştuğunda ilişki stok hareketinin ikinci kez yazılmamasını belirleyen kaynak kanıtlarından biridir.

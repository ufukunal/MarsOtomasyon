# Faz 6 iade kaynakları ve karantina

**Veritabanı: DÖNEM**

Faz 6 mevcut `documents`, `document_lines`, `document_relations`, `contact_transactions`, `stock_movements` ve `stock_balances` çekirdeğini kullanır.

Yeni veri yalnız iki boşluğu kapatır:

1. önceki dönem / kaynaksız iadede cross-DB source snapshot,
2. karantina miktarının gerçek kaynağı.

## return_sources

Aynı-period kaynaklı iadede esas bağlantı yine `document_lines.source_line_id` ve `document_relations` olur.

Önceki dönem veya kaynaksız iadede:

- cross-DB FK kurulmaz,
- kaynak period kimliği scalar tutulur,
- gerekli frozen değerler snapshot edilir.

```php
Schema::connection('period')->create('return_sources', function (Blueprint $table) {
    $table->id();
    $table->foreignId('return_line_id')
        ->constrained('document_lines')
        ->cascadeOnDelete();

    $table->string('source_mode', 20); // same_period | prior_period | manual

    $table->unsignedBigInteger('source_period_id')->nullable();
    $table->string('source_period_label')->nullable();
    $table->unsignedBigInteger('source_document_id')->nullable();
    $table->unsignedBigInteger('source_line_id')->nullable();

    $table->string('source_document_no')->nullable();
    $table->string('source_document_type', 40)->nullable();

    $table->decimal('source_quantity', 18, 3)->nullable();
    $table->decimal('source_unit_price', 18, 4)->nullable();
    $table->decimal('source_discount_rate', 7, 4)->nullable();
    $table->decimal('source_discount_amount', 18, 4)->nullable();
    $table->decimal('source_vat_rate', 7, 4)->nullable();
    $table->foreignId('source_unit_id')->nullable()->constrained('units')->restrictOnDelete();
    $table->decimal('source_conversion_factor', 18, 6)->nullable();
    $table->decimal('source_unit_cost', 18, 4)->nullable();
    $table->char('source_currency', 3)->nullable();
    $table->decimal('source_exchange_rate', 18, 6)->nullable();

    $table->string('manual_reason_code', 40)->nullable();
    $table->text('manual_reason_text')->nullable();

    $table->timestamps();

    $table->unique('return_line_id');
});
```

`source_period_id` Master periods id'sinin scalar snapshot'ıdır; FK değildir.

## quarantine_entries

```php
Schema::connection('period')->create('quarantine_entries', function (Blueprint $table) {
    $table->id();

    $table->foreignId('product_id')->constrained('products');
    $table->foreignId('location_id')->constrained('locations');

    $table->foreignId('source_document_id')
        ->nullable()
        ->constrained('documents')
        ->restrictOnDelete();

    $table->foreignId('source_line_id')
        ->nullable()
        ->constrained('document_lines')
        ->restrictOnDelete();

    $table->decimal('quantity', 18, 3);
    $table->decimal('released_quantity', 18, 3)->default(0);
    $table->decimal('scrapped_quantity', 18, 3)->default(0);
    $table->decimal('unit_cost', 18, 4);

    $table->string('status', 20)->default('pending');
    $table->text('decision_note')->nullable();

    $table->unsignedInteger('version')->default(1);

    $table->unsignedBigInteger('created_by')->nullable();
    $table->string('created_by_name')->nullable();
    $table->timestamps();

    $table->index(['product_id','location_id','status']);
});
```

Kalan:

```
pending_quantity = quantity - released_quantity - scrapped_quantity
```

CHECK:

- quantity > 0
- released_quantity >= 0
- scrapped_quantity >= 0
- released_quantity + scrapped_quantity <= quantity
- status in pending|partial|released|scrapped

## Return reason

K-113 sabit kodları:

- wrong_product
- damaged
- defective
- quantity_error
- customer_request
- supplier_return
- other

Ayrı return_reasons kartı yoktur.

## Dönem devri

Açık quarantine entry yeni döneme açık miktarı ve gerekli product/location/cost/source snapshot bilgisiyle taşınır. Geçmiş iade belgesi taşınmaz.

## Integrity

- `integrity:returns`: return source snapshot ve source quantity limitleri.
- `integrity:quarantine`: aktif pending toplamı = stock_balances.quarantine.

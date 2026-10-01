# number_series

**Veritabanı: DÖNEM**

Period DB zaten tek şirkete/yıla aittir; `company_id` yoktur.

## Şema

```php
Schema::connection('period')->create('number_series', function (Blueprint $table) {
    $table->id();
    $table->string('document_type', 40);
    $table->string('prefix', 10);
    $table->unsignedSmallInteger('year');
    $table->unsignedBigInteger('last_number')->default(0);
    $table->unsignedTinyInteger('padding')->default(5);
    $table->timestamps();
    $table->unique(['document_type','year']);
});
```

## Kural

Numara yalnız kesinleştirmede, çağıran DB transaction içinde `lockForUpdate()` ile üretilir. Taslakta numara yoktur. Rollback numara artışını da geri alır.

Format örneği: `SF-2027-00001`. Yeni period/yıl kendi sayaçlarından başlayabilir.

Seed belge türleri: quote, sales_order, dispatch, sales_invoice, proforma, purchase_order, goods_receipt, supplier_invoice, sales_return, purchase_return, transfer, stock_count, warehouse_slip, production_order, collection, payment, contact_debit_credit.

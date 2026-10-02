# stock_movements

**Veritabanı: DÖNEM**

Stoğun tek gerçek hareket kaynağıdır. `company_id` yoktur.

```php
Schema::connection('period')->create('stock_movements', function (Blueprint $table) {
    $table->id();
    $table->foreignId('product_id')->constrained('products');
    $table->foreignId('location_id')->constrained('locations');

    $table->date('movement_date');
    $table->string('direction', 3); // in|out
    $table->string('reason', 30);
    $table->decimal('quantity', 18, 3); // HER ZAMAN temel birim ve pozitif
    $table->decimal('unit_cost', 18, 4);
    $table->decimal('total_cost', 18, 4);
    $table->decimal('balance_after', 18, 3);
    $table->decimal('avg_cost_after', 18, 4);

    $table->string('document_type', 40)->nullable();
    $table->unsignedBigInteger('document_id')->nullable();
    $table->string('document_no', 40)->nullable();

    $table->text('note')->nullable();
    $table->unsignedBigInteger('created_by')->nullable(); // Master user scalar
    $table->string('created_by_name')->nullable();
    $table->timestamps();

    $table->index(['product_id','location_id','movement_date']);
    $table->index(['document_type','document_id']);
});
```

CHECK: quantity > 0, direction in/out, unit_cost >= 0, total_cost tutarlı.

Hareket silinmez/değiştirilmez; ters hareket yazılır. Tüm yazım `RecordStockMovement` üzerinden, transaction ve `EnsurePeriodOpen(document_date)` içinde yapılır.

Satışta irsaliye stok çıkışı üretir. İrsaliyeden satış faturası stok üretmez; doğrudan satış faturası stok çıkışı üretir.

Faz 4'te goods_receipt stok hareketi üretmez. Purchase_invoice posting'i `direction=in, reason=purchase` hareketi üretir; quantity temel birimde, unit_cost şirket temel para biriminde net alış maliyetidir.

Belge satırı kullanıcı biriminde quantity yanında `base_quantity + conversion_factor` dondurur; stock_movements.quantity daima `base_quantity`dır.

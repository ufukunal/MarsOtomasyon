# posting_periods

**Veritabanı: DÖNEM**

## Şema

```php
Schema::connection('period')->create('posting_periods', function (Blueprint $table) {
    $table->id();
    $table->unsignedSmallInteger('year');
    $table->unsignedTinyInteger('month');
    $table->string('status', 10)->default('open'); // open | closed

    // Master users'a cross-DB FK YOK
    $table->unsignedBigInteger('closed_by')->nullable();
    $table->string('closed_by_name')->nullable();
    $table->timestamp('closed_at')->nullable();

    $table->unsignedBigInteger('reopened_by')->nullable();
    $table->string('reopened_by_name')->nullable();
    $table->timestamp('reopened_at')->nullable();
    $table->text('reopen_reason')->nullable();

    $table->timestamps();
    $table->unique(['year','month']);
});
```

CHECK: month 1..12; status open|closed.

## Kurallar

`EnsurePeriodOpen(document_date)` tek kontrol noktasıdır. Kapalı aya yeni kesinleşmiş belge/hareket yazılamaz.

Kapanmış yılı/ayı yeniden açmak role sabit değildir; özel yeniden-açma izni gerekir ve gerekçe zorunludur. Açma/kapama period activity_log'a actor snapshot ile yazılır.

Satırı olmayan ay varsayılan açık kabul edilecekse bu davranış Action içinde açık testle korunur; sessiz varsayım yapılmaz.

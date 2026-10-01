# contacts ve yan tabloları

**Veritabanı: DÖNEM**

Cari müşteri/tedarikçi tek karttır. `company_id` yoktur.

## contacts

```php
Schema::connection('period')->create('contacts', function (Blueprint $table) {
    $table->id();
    $table->string('code', 30)->unique();
    $table->string('title');
    $table->string('type', 10)->default('legal');
    $table->string('tax_office')->nullable();
    $table->string('tax_number', 20)->nullable();
    $table->string('national_id', 11)->nullable();
    $table->text('address')->nullable();
    $table->string('city', 60)->nullable();
    $table->string('district', 60)->nullable();
    $table->string('phone', 30)->nullable();
    $table->string('email')->nullable();

    $table->unsignedSmallInteger('term_days')->nullable();
    $table->decimal('risk_limit', 18, 4)->default(0);
    $table->decimal('discount_rate', 7, 4)->default(0);
    $table->foreignId('price_list_id')->nullable()->constrained('price_lists');

    // başka şirketten kopya provenance; cross-DB FK DEĞİL
    $table->unsignedBigInteger('source_company_id')->nullable();
    $table->unsignedBigInteger('source_record_id')->nullable();

    $table->string('search_index')->nullable();
    $table->boolean('is_active')->default(true);
    $table->unsignedInteger('version')->default(1);
    $table->timestamps();

    $table->index('tax_number');
    $table->index('source_company_id');
});
```

Kod pasifleşse bile başka karta verilmez; fiziksel silme/SoftDeletes yoktur. Düzenleme `version` optimistic lock ile yapılır. Aynı şirket dönem devrinde ID+code korunur. Şirketler arası kopyada yeni ID oluşur.

## Yan tablolar

`contact_categories`: id, name unique, color, is_active.
Pivot: contact_id + category_id gerçek period FK.
`contact_addresses`, `contact_people`, `contact_banks`: contact_id gerçek FK.

Bu period tablolarının hiçbirinde `company_id` yoktur.

## Bakiye / risk

Bakiye contacts üzerinde saklanmaz; `contact_transactions` toplamıdır. Yaşlandırma FIFO rapor hesabıdır. Risk limiti blok değil uyarıdır; sipariş ekranı cari bakiye + yeni sipariş + portföy kıymet riskini ayrıca gösterir.

TC kimlik maskelenir; tam görüntü ayrı izindir.

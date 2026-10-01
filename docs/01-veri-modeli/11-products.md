# products ve yan tabloları

**Veritabanı: DÖNEM**

Her varyant ayrı ürün kartıdır. `company_id` yoktur.

## products

```php
Schema::connection('period')->create('products', function (Blueprint $table) {
    $table->id();
    $table->string('code', 40)->unique();
    $table->string('name');
    $table->text('description')->nullable();

    $table->foreignId('category_id')->nullable()->constrained('product_categories');
    $table->foreignId('brand_id')->nullable()->constrained('brands');
    $table->foreignId('unit_id')->constrained('units');

    $table->string('barcode', 40)->nullable()->index();
    $table->decimal('vat_rate', 7, 4)->default(20);
    $table->decimal('list_price', 18, 4)->default(0);
    $table->char('currency', 3)->default('TRY');

    $table->string('kind', 12)->default('normal');
    $table->foreignId('variant_group_id')->nullable()->constrained('variant_groups');

    $table->boolean('allow_negative_stock')->default(false);
    $table->decimal('min_stock', 18, 3)->default(0);
    $table->string('channel_stock_mode', 20)->default('stock'); // stock|production|manual

    // cross-company provenance; FK yok
    $table->unsignedBigInteger('source_company_id')->nullable();
    $table->unsignedBigInteger('source_record_id')->nullable();

    $table->string('search_index')->nullable();
    $table->boolean('is_active')->default(true);
    $table->unsignedInteger('version')->default(1);
    $table->timestamps();
});
```

Kod tekrar kullanılmaz; fiziksel silme/SoftDeletes yoktur ve `version` optimistic lock kullanılır. Aynı şirket dönem devrinde ID+code korunur; şirketler arası kopyada yeni ID oluşur.

## Yan tablolar

- product_categories: id, name, parent_id nullable, is_active
- brands: id, name, is_active
- units: id, code, name, is_base
- unit_conversions: id, from_unit_id, to_unit_id, factor decimal(18,6)
- variant_groups / variant_attributes / product_variant_values
- product_sets
- config_definitions / config_options
- price_lists / price_list_items

Hepsi period DB'dedir ve `company_id` taşımaz. Aynı period içindeki ilişkiler gerçek FK'dir.

Konfigüratör seçeneğinde fiyat alanı yoktur. Setin kendi fiziksel stoğu yoktur; satılabilir set miktarı bileşenlerin kullanılabilir stoklarından hesaplanır.

Pazaryerlerine kartlar varyantsız ayrı ürün olarak gider; varyant grup B2B/kendi site içindir.

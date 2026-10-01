# products ve yan tabloları

**Veritabanı: DÖNEM**

`company_id` kolonu **yoktur** — veritabanı zaten o şirkete ve yıla aittir.



## Amaç

Ürün kartı. **Her varyant ayrı karttır**; varyant grubu ayrı kartları
tek ürün gibi gösterir.

## products

```php
Schema::connection('period')->create('products', function (Blueprint $table) {
    $table->id();
    $table->string('code', 40);
    $table->string('name');
    $table->text('description')->nullable();

    $table->foreignId('category_id')->nullable()->constrained('product_categories');
    $table->foreignId('brand_id')->nullable()->constrained('brands');
    $table->foreignId('unit_id')->constrained('units');

    $table->string('barcode', 40)->nullable();
    $table->decimal('vat_rate', 7, 4)->default(20);
    $table->decimal('list_price', 18, 4)->default(0);       // KDV HARİÇ
    $table->char('currency', 3)->default('TRY');

    $table->string('kind', 12)->default('normal');           // normal | set | configurable
    $table->foreignId('variant_group_id')->nullable()->constrained('variant_groups');

    $table->boolean('allow_negative_stock')->default(false); // ürün bazında izin
    $table->decimal('min_stock', 18, 3)->default(0);

    $table->foreignId('source_company_id')->nullable()->constrained('companies');
    $table->unsignedBigInteger('source_record_id')->nullable();

    $table->boolean('is_active')->default(true);
    $table->timestamps();
    $table->softDeletes();

    $table->unique(['company_id','code']);
    $table->index(['company_id','barcode']);
    $table->index(['company_id','variant_group_id']);
});
```

## product_categories / brands
`id, company_id, name, parent_id (kategori için), is_active` — basit ağaç.

## units / unit_conversions

```php
// units: id, company_id, code (ADET, KUTU, KG), name, is_base
// unit_conversions: id, company_id, from_unit_id, to_unit_id, factor decimal(18,6)
```

## variant_groups / variant_attributes / product_variant_values

```php
// variant_groups:      id, company_id, name, is_active
// variant_attributes:  id, variant_group_id, name (Renk, Ölçü), sort_order
// product_variant_values: id, product_id, variant_attribute_id, value (Gold, 80cm)
```

Grup, B2B ve kendi e-ticaret sitelerinde **tek ürün** olarak yayınlanır,
kartlar varyant olur. Pazaryerlerine şimdilik düz kart gönderilir (A-007).

## product_sets

```php
Schema::connection('period')->create('product_sets', function (Blueprint $table) {
    $table->id();
    $table->foreignId('set_product_id')->constrained('products');
    $table->foreignId('component_product_id')->constrained('products');
    $table->decimal('quantity', 18, 3);
    $table->timestamps();
    $table->unique(['set_product_id','component_product_id'], 'product_sets_unique');
});
```

**Setin kendi stoğu yoktur.** Satılabilir adet:
`min(bileşen_stoğu ÷ gereken_miktar)`. Bir bileşen tek seti bile
karşılayamıyorsa set adedi **sıfırdır** ve tüm kanallarda satışa kapanır.

## config_definitions / config_options

```php
// config_definitions: id, company_id, product_id, name (Gövde, Kristal, Duy), is_required, sort_order
// config_options:     id, config_definition_id, component_product_id, label, sort_order
//   NOT: fiyat farkı YOK — konfigüratör yalnız ürün özelliklerini tanımlar (A-002)
```

Sipariş satırı ürün koduna değil, seçilen bileşenlere bağlanır; satır
kaydedilirken bileşen listesi **dondurulur** (Faz 3).

## price_lists / price_list_items

```php
// price_lists:      id, company_id, name, currency, vat_included (bool), is_default, is_active
// price_list_items: id, price_list_id, product_id, price decimal(18,4), valid_from, valid_to
```

Fiyat girişinde KDV dahil/hariç seçilir; **veritabanında her zaman hariç
saklanır** (K-009).

# Faz 8 — Üretim ve fason veri modeli

**Veritabanı: DÖNEM**

Faz 8 basit iç üretim + fason üretimi kapsar. Lot/parti takibi yoktur.

## production_recipes

```php
Schema::connection('period')->create('production_recipes', function (Blueprint $table) {
    $table->id();
    $table->foreignId('product_id')->constrained('products')->restrictOnDelete();

    $table->string('number', 40);
    $table->unsignedSmallInteger('revision_no');
    $table->decimal('output_quantity', 18, 3);

    $table->boolean('is_active')->default(true);
    $table->unsignedInteger('version')->default(1);

    $table->unsignedBigInteger('created_by')->nullable();
    $table->string('created_by_name')->nullable();
    $table->timestamps();

    $table->unique(['product_id','revision_no']);
});
```

Bir mamulde aynı anda tek aktif reçete vardır.

## production_recipe_lines

```php
Schema::connection('period')->create('production_recipe_lines', function (Blueprint $table) {
    $table->id();

    $table->foreignId('production_recipe_id')
        ->constrained('production_recipes')
        ->cascadeOnDelete();

    $table->foreignId('component_product_id')
        ->constrained('products')
        ->restrictOnDelete();

    $table->foreignId('unit_id')
        ->constrained('units')
        ->restrictOnDelete();

    $table->decimal('quantity', 18, 3);
    $table->decimal('base_quantity', 18, 3);
    $table->decimal('conversion_factor', 18, 6);

    $table->unsignedInteger('version')->default(1);
    $table->timestamps();
});
```

Fire yüzdesi alanı yoktur.

## production_orders

```php
Schema::connection('period')->create('production_orders', function (Blueprint $table) {
    $table->id();

    $table->string('number', 40)->nullable();
    $table->date('document_date');

    $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
    $table->foreignId('recipe_id')->constrained('production_recipes')->restrictOnDelete();

    $table->decimal('planned_quantity', 18, 3);
    $table->decimal('completed_quantity', 18, 3)->default(0);
    $table->decimal('cancelled_quantity', 18, 3)->default(0);

    $table->string('production_type', 20)->default('internal');
    // internal | subcontract

    $table->foreignId('subcontractor_contact_id')
        ->nullable()
        ->constrained('contacts')
        ->restrictOnDelete();

    $table->foreignId('subcontractor_location_id')
        ->nullable()
        ->constrained('locations')
        ->restrictOnDelete();

    $table->foreignId('source_sales_order_id')
        ->nullable()
        ->constrained('documents')
        ->restrictOnDelete();

    $table->string('status', 30)->default('draft');
    // draft | confirmed | in_progress | completed | cancelled

    $table->unsignedInteger('version')->default(1);

    $table->unsignedBigInteger('created_by')->nullable();
    $table->string('created_by_name')->nullable();
    $table->unsignedBigInteger('confirmed_by')->nullable();
    $table->string('confirmed_by_name')->nullable();

    $table->timestamps();

    $table->index(['product_id','status']);
});
```

## production_order_components

Reçete snapshot'ıdır.

```php
Schema::connection('period')->create('production_order_components', function (Blueprint $table) {
    $table->id();

    $table->foreignId('production_order_id')
        ->constrained('production_orders')
        ->cascadeOnDelete();

    $table->foreignId('component_product_id')
        ->constrained('products')
        ->restrictOnDelete();

    $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();

    $table->decimal('planned_quantity', 18, 3);
    $table->decimal('planned_base_quantity', 18, 3);
    $table->decimal('conversion_factor', 18, 6);

    $table->timestamps();
});
```

## production_completions

```php
Schema::connection('period')->create('production_completions', function (Blueprint $table) {
    $table->id();

    $table->foreignId('production_order_id')
        ->constrained('production_orders')
        ->restrictOnDelete();

    $table->date('completion_date');
    $table->decimal('completed_quantity', 18, 3);

    $table->decimal('material_cost_total', 18, 4);
    $table->decimal('subcontract_service_cost_total', 18, 4)->default(0);
    $table->decimal('production_cost_total', 18, 4);
    $table->decimal('production_unit_cost', 18, 4);

    $table->foreignId('reversal_of_id')
        ->nullable()
        ->constrained('production_completions')
        ->restrictOnDelete();

    $table->unsignedBigInteger('created_by')->nullable();
    $table->string('created_by_name')->nullable();
    $table->timestamps();

    $table->index(['production_order_id','completion_date']);
});
```

## production_consumptions

Her completion'daki gerçek component tüketimi.

```php
Schema::connection('period')->create('production_consumptions', function (Blueprint $table) {
    $table->id();

    $table->foreignId('production_completion_id')
        ->constrained('production_completions')
        ->cascadeOnDelete();

    $table->foreignId('component_product_id')
        ->constrained('products')
        ->restrictOnDelete();

    $table->foreignId('location_id')
        ->constrained('locations')
        ->restrictOnDelete();

    $table->decimal('consumed_quantity', 18, 3);
    $table->decimal('fire_quantity', 18, 3)->default(0);

    $table->decimal('unit_cost', 18, 4);
    $table->decimal('total_cost', 18, 4);

    $table->foreignId('stock_movement_id')
        ->constrained('stock_movements')
        ->restrictOnDelete();

    $table->timestamps();
});
```

## production_outputs

K-139 gereği tek completion birden fazla target location'a bölünebilir.

```php
Schema::connection('period')->create('production_outputs', function (Blueprint $table) {
    $table->id();

    $table->foreignId('production_completion_id')
        ->constrained('production_completions')
        ->cascadeOnDelete();

    $table->foreignId('location_id')
        ->constrained('locations')
        ->restrictOnDelete();

    $table->decimal('quantity', 18, 3);

    $table->foreignId('stock_movement_id')
        ->constrained('stock_movements')
        ->restrictOnDelete();

    $table->timestamps();
});
```

```
SUM(production_outputs.quantity) = production_completions.completed_quantity
```

## Fason hizmet ilişkisi

Normal purchase_invoice kullanılır.

Yeni relation type:

- `subcontract_service_source`

source = purchase_invoice, target = production_order.

Geç gelen hizmet maliyeti `inventory_cost_adjustments.reason=subcontract_late_cost` ile işlenir.

Faz 8 migration'ı, Faz 7'de oluşturulan `inventory_cost_adjustments` tablosunu production provenance için genişletir:

```php
Schema::connection('period')->table('inventory_cost_adjustments', function (Blueprint $table) {
    $table->foreignId('production_completion_id')
        ->nullable()
        ->constrained('production_completions')
        ->restrictOnDelete();
});
```

`reason=subcontract_late_cost` kaydında `production_completion_id` zorunludur; import_file/import_file_line alanları null olabilir. Böylece Faz 7 import adjustment ile Faz 8 production adjustment aynı gerçek kaynak tabloda, fakat kaynak provenance'ı açık biçimde ayrılır.

## Location türü

`locations.kind` Faz 8'de `subcontractor` değerini destekleyecek şekilde genişletilir:

- normal
- vehicle
- subcontractor

Subcontractor location satış rezervasyon/sevki için uygun değildir.

Opsiyonel ilişki:

- `subcontractor_contact_id`

yalnız `kind=subcontractor` iken doludur.

## Dönem devri

- production recipes/revisions kopyalanır,
- açık production_orders taşınmaz,
- subcontractor location kartı taşınır,
- fason location fiziksel stock balance açılışı normal location mantığıyla taşınır.

## Integrity

- `integrity:production`
- `integrity:recipes`

en az recipe snapshot, consumption/output totals, completion remaining, production cost ve stock movement eşleşmelerini doğrular.

# Faz 7 İthalat — import_files, import_expenses, allocations, inventory_cost_adjustments

**Veritabanı: DÖNEM**

Faz 7, Faz 4 purchase_invoice stok/cari/maliyet çekirdeğini yeniden kullanır. İthalat dosyası fiziksel stok belgesi değildir; ek maliyetleri toplar, dağıtır ve finalize anında yalnız maliyet düzeltmesi üretir.

## import_files

```php
Schema::connection('period')->create('import_files', function (Blueprint $table) {
    $table->id();

    $table->string('number', 40)->nullable();
    $table->date('document_date');
    $table->string('status', 30)->default('draft');
    // draft | cost_collection | finalized | adjusted

    $table->text('notes')->nullable();

    $table->unsignedInteger('version')->default(1);

    $table->unsignedBigInteger('created_by')->nullable();
    $table->string('created_by_name')->nullable();
    $table->unsignedBigInteger('finalized_by')->nullable();
    $table->string('finalized_by_name')->nullable();
    $table->timestamp('finalized_at')->nullable();

    $table->timestamps();

    $table->unique('number');
    $table->index(['status','document_date']);
});
```

## import_file_lines

Bir purchase_invoice line bir import dosyasına tam satır bazında bağlanır.

```php
Schema::connection('period')->create('import_file_lines', function (Blueprint $table) {
    $table->id();

    $table->foreignId('import_file_id')
        ->constrained('import_files')
        ->cascadeOnDelete();

    $table->foreignId('purchase_invoice_line_id')
        ->constrained('document_lines')
        ->restrictOnDelete();

    $table->foreignId('product_id')
        ->constrained('products')
        ->restrictOnDelete();

    $table->decimal('base_quantity', 18, 3);
    $table->decimal('purchase_value_base', 18, 4);
    $table->decimal('purchase_unit_cost_base', 18, 4);

    $table->timestamps();

    $table->unique('purchase_invoice_line_id');
    $table->index(['import_file_id','product_id']);
});
```

`purchase_value_base` kaynak purchase_invoice frozen kur + net maliyet hesabının base currency karşılığıdır.

## import_expenses

```php
Schema::connection('period')->create('import_expenses', function (Blueprint $table) {
    $table->id();

    $table->foreignId('import_file_id')
        ->constrained('import_files')
        ->cascadeOnDelete();

    $table->string('expense_type', 40);
    // freight | customs_duty | insurance | storage |
    // customs_brokerage | port_terminal | other

    $table->string('source_type', 30);
    // purchase_invoice | manual

    $table->foreignId('source_document_id')
        ->nullable()
        ->constrained('documents')
        ->restrictOnDelete();

    $table->foreignId('source_document_line_id')
        ->nullable()
        ->constrained('document_lines')
        ->restrictOnDelete();

    $table->char('currency', 3);
    $table->decimal('exchange_rate', 18, 6);
    $table->decimal('amount', 18, 4);
    $table->decimal('amount_base', 18, 4);

    $table->string('allocation_method', 30);
    // purchase_value | quantity | manual

    $table->boolean('is_inventory_cost')->default(true);

    $table->text('description')->nullable();

    $table->unsignedInteger('version')->default(1);
    $table->timestamps();

    $table->index(['import_file_id','expense_type']);
});
```

Kurallar:

- purchase_invoice kaynaklı expense kendi document frozen currency/exchange_rate değerini kullanır.
- K-257 gereği faturalı navlun/sigorta/müşavirlik vb. expense mümkün olduğunda `line_kind=service` olan `source_document_line_id` ile satır seviyesinde bağlanır; amount bu service satırın frozen net tutarıdır.
- Mixed stock+service purchase_invoice'da stok ürün satırları expense amount'a dahil edilmez.
- manual expense için currency/exchange_rate snapshot girilir.
- `other` ise description zorunlu.
- indirilebilir ithalat KDV'si `is_inventory_cost=false` olmalıdır.
- K-118 kapsamındaki direct import costs `true`.

## import_expense_allocations

```php
Schema::connection('period')->create('import_expense_allocations', function (Blueprint $table) {
    $table->id();

    $table->foreignId('import_expense_id')
        ->constrained('import_expenses')
        ->cascadeOnDelete();

    $table->foreignId('import_file_line_id')
        ->constrained('import_file_lines')
        ->cascadeOnDelete();

    $table->decimal('allocation_base', 18, 4);
    $table->decimal('allocated_amount_base', 18, 4);

    $table->timestamps();

    $table->unique(
        ['import_expense_id','import_file_line_id'],
        'import_expense_allocations_unique'
    );
});
```

Her inventory-cost expense için:

```
SUM(allocated_amount_base) = expense.amount_base
```

Yuvarlama farkı K-128 gereği deterministik son uygun satıra verilir.

## inventory_cost_adjustments

Fiziksel stock movement değildir.

```php
Schema::connection('period')->create('inventory_cost_adjustments', function (Blueprint $table) {
    $table->id();

    $table->foreignId('product_id')
        ->constrained('products')
        ->restrictOnDelete();

    $table->foreignId('import_file_id')
        ->nullable()
        ->constrained('import_files')
        ->restrictOnDelete();

    $table->foreignId('import_file_line_id')
        ->nullable()
        ->constrained('import_file_lines')
        ->restrictOnDelete();

    $table->foreignId('adjustment_of_id')
        ->nullable()
        ->constrained('inventory_cost_adjustments')
        ->restrictOnDelete();

    $table->date('adjustment_date');

    $table->decimal('quantity_basis', 18, 3);
    $table->decimal('amount_base', 18, 4);
    $table->decimal('unit_adjustment_base', 18, 4);

    $table->decimal('moving_average_before', 18, 4);
    $table->decimal('moving_average_after', 18, 4);

    $table->string('reason', 30); // import_finalize | import_late_cost | subcontract_late_cost | reversal

    $table->unsignedBigInteger('created_by')->nullable();
    $table->string('created_by_name')->nullable();
    $table->timestamps();

    $table->index(['product_id','adjustment_date']);
});
```

### Faz 8 production provenance genişletmesi

Faz 8, bu tabloya `production_completion_id` nullable FK ekler. Bu kolon `production_completions` tablosu Faz 8'de oluşturulduktan sonra ALTER migration ile eklenir; Faz 7 migration'ı henüz var olmayan production tablosuna FK kurmaz.

Kaynak kuralları:

- import_finalize/import_late_cost → import_file_id/import_file_line_id dolu,
- subcontract_late_cost → production_completion_id dolu,
- reversal → adjustment_of_id dolu ve terslenen kaydın source provenance'ı izlenebilir.

## product_costs

K-124:

- `moving_average` geçerli maliyet,
- `import_cost` son finalized import unit cost snapshot'ı.

## CHECK / integrity

- exchange_rate > 0
- amount > 0
- amount_base >= 0
- allocation_method in purchase_value|quantity|manual
- source_type in purchase_invoice|manual
- source_type=purchase_invoice ise source_document_id zorunlu; service-line kaynak kullanılıyorsa source_document_line_id aynı document'a ait posted purchase_invoice `line_kind=service` satırı olmalı
- import file finalized ise lines/expenses immutable
- inventory-cost expenses tam dağıtılmış olmalı
- purchase_invoice_line tek import file'a bağlı olmalı
- adjustment fiziksel quantity değiştirmemeli

Komutlar:

- `integrity:imports`
- `integrity:cost-adjustments`

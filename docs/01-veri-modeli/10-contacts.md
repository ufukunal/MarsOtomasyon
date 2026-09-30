# contacts ve yan tabloları

## Amaç

Cari kartı. **Müşteri ve tedarikçi ayrı kart değildir**; tek kart, kategoriyle
ayrılır. Bakiye tektir.

## contacts

```php
Schema::create('contacts', function (Blueprint $table) {
    $table->id();
    $table->foreignId('company_id')->constrained();
    $table->string('code', 30);
    $table->string('title');                                  // unvan
    $table->string('type', 10)->default('legal');             // legal | real
    $table->string('tax_office')->nullable();
    $table->string('tax_number', 20)->nullable();
    $table->string('national_id', 11)->nullable();            // TC
    $table->text('address')->nullable();
    $table->string('city', 60)->nullable();
    $table->string('district', 60)->nullable();
    $table->string('phone', 30)->nullable();
    $table->string('email')->nullable();

    $table->unsignedSmallInteger('term_days')->nullable();    // boş = şirket varsayılanı
    $table->decimal('risk_limit', 18, 4)->default(0);
    $table->decimal('discount_rate', 7, 4)->default(0);       // belgeye otomatik gelir

    $table->foreignId('source_company_id')->nullable()->constrained('companies');
    $table->unsignedBigInteger('source_record_id')->nullable();

    $table->boolean('is_active')->default(true);
    $table->timestamps();
    $table->softDeletes();

    $table->unique(['company_id','code']);
    $table->index(['company_id','title']);
    $table->index(['company_id','tax_number']);
});
```

## contact_categories / contact_contact_category

Kategoriler: **Tedarikçi, Cari, İnternet Müşterisi, Mağaza Müşterisi**.
Bir kart birden çok kategori taşıyabilir (çoktan çoğa).

```php
Schema::create('contact_categories', function (Blueprint $table) {
    $table->id();
    $table->foreignId('company_id')->constrained();
    $table->string('name', 60);
    $table->string('color', 20)->nullable();
    $table->timestamps();
    $table->unique(['company_id','name']);
});
```

## contact_addresses

```php
$table->foreignId('contact_id')->constrained()->cascadeOnDelete();
$table->string('kind', 10);          // billing | shipping
$table->string('title', 60);
$table->text('address'); $table->string('city',60); $table->string('district',60)->nullable();
$table->boolean('is_default')->default(false);
```

## contact_people

```php
$table->foreignId('contact_id')->constrained()->cascadeOnDelete();
$table->string('name'); $table->string('role',60)->nullable();
$table->string('phone',30)->nullable(); $table->string('email')->nullable();
$table->boolean('is_primary')->default(false);
```

## contact_banks

```php
$table->foreignId('contact_id')->constrained()->cascadeOnDelete();
$table->string('bank_name',60); $table->string('branch',60)->nullable();
$table->string('iban',34)->nullable(); $table->char('currency',3)->default('TRY');
```

## Kurallar

- `code` şirket içinde benzersiz, otomatik üretilebilir (`CR` + sıra)
- `term_days` boşsa `companies.default_term_days` kullanılır
- `risk_limit` aşımında **uyarı** verilir, işlem engellenmez (K-007 benzeri)
- `discount_rate` belgelere otomatik gelir, belgede değiştirilebilir
- Bakiye bu tabloda **tutulmaz**; `contact_transactions` tablosundan hesaplanır
  (Faz 3'te gelir). Faz 1'de bakiye kolonu 0 gösterir.

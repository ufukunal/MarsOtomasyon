# companies

**Veritabanı: MASTER**

Şirket üstü tablo; `company_id` taşımaz.



Şirket kartı. Resmi ve gayri resmi işleyiş **ayrı birer şirkettir**.
Her şirketin her yıl için ayrı bir dönem veritabanı vardır.

## Şema

```php
Schema::connection('master')->create('companies', function (Blueprint $table) {
    $table->id();
    $table->string('code', 20)->unique();          // ABCHOLDING, XYZLTD
    $table->string('name');
    $table->string('legal_name')->nullable();
    $table->string('db_prefix', 30)->unique();     // ABCHolding → ABCHolding_2026
    $table->string('tax_office')->nullable();
    $table->string('tax_number', 20)->nullable();
    $table->text('address')->nullable();
    $table->string('city', 60)->nullable();
    $table->string('phone', 30)->nullable();
    $table->string('email')->nullable();
    $table->string('logo_path')->nullable();
    $table->unsignedSmallInteger('default_term_days')->default(30);
    $table->decimal('cost_deviation_threshold', 7, 4)->default(25);
    $table->char('base_currency', 3)->default('TRY');
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});

// Şirketler arası kart kopyalama izni (eski company_copy_permissions)
Schema::connection('master')->create('company_copy_permissions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('source_company_id')->constrained('companies');
    $table->foreignId('target_company_id')->constrained('companies');
    $table->string('type', 20);                 // contact | product
    $table->boolean('is_active')->default(true);
    $table->timestamps();
    $table->unique(['source_company_id','target_company_id','type'], 'ccp_unique');
});
```

## db_prefix

Dönem veritabanı adı `{db_prefix}_{year}` olarak üretilir:
`ABCHolding` + `2026` → `ABCHolding_2026`

Yalnızca harf, rakam ve alt çizgi. Kayıt sonrası **değiştirilemez** —
mevcut veritabanı adları buna bağlıdır.

## Örnek veri (seed)

| code | name | db_prefix |
|---|---|---|
| ABCHOLDING | ABC Holding | ABCHolding |
| XYZLTD | XYZ Ltd. Şti. | XYZltd |

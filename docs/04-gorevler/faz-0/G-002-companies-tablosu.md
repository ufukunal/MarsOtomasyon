# G-002 — companies tablosu ve modeli

## Amaç
Şirket kaydı. Resmi ve gayri resmi işleyiş ayrı birer şirkettir.

## Önkoşul
G-001

## Dokunulacak dosyalar
- `database/migrations/xxxx_create_companies_table.php`
- `app/Models/Company.php`
- `database/seeders/CompanySeeder.php`

## Şema

```php
Schema::create('companies', function (Blueprint $table) {
    $table->id();
    $table->string('code', 20)->unique();
    $table->string('name');
    $table->string('legal_name')->nullable();
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
```

## Model kuralları
- `$fillable` tüm alanlar (`id` hariç)
- `code` mutator: büyük harfe çevir
- `casts`: `is_active` → bool, `cost_deviation_threshold` → decimal:4
- `users()` → `belongsToMany(User::class)` (`company_user` pivot)

## Seed
| code | name | legal_name |
|---|---|---|
| MARS | Mars Aydınlatma | Mars Aydınlatma San. Tic. A.Ş. |
| MARS2 | Mars Ticaret | Mars Ticaret Ltd. Şti. |

## Kabul ölçütü
```bash
php artisan migrate:fresh --seed
php artisan tinker --execute="echo App\Models\Company::count();"   # 2
```

## İstem
> companies tablosu için migration, Company modeli ve CompanySeeder yaz.
> Şema yukarıdaki gibi olsun, birebir. Model fillable, cast ve code mutator
> içersin. Seeder iki şirket eklesin: MARS ve MARS2. Başka tablo oluşturma.

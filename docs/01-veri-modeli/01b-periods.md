# periods

**Veritabanı: MASTER**

Şirket üstü tablo; `company_id` taşımaz.



Hangi şirketin hangi yılı için hangi veritabanının olduğunu tutar.
Bağlantı yönetiminin dayandığı tablo.

## Şema

```php
Schema::connection('master')->create('periods', function (Blueprint $table) {
    $table->id();
    $table->foreignId('company_id')->constrained();
    $table->unsignedSmallInteger('year');
    $table->string('database_name', 64)->unique();     // ABCHolding_2026
    $table->date('starts_on');                          // 01.01.2026
    $table->date('ends_on');                            // 31.12.2026
    $table->string('status', 12)->default('active');    // active | closed | archived
    $table->foreignId('carried_from_period_id')->nullable()->constrained('periods');
    $table->timestamp('carried_at')->nullable();        // devir yapıldı mı
    $table->timestamp('closed_at')->nullable();
    $table->timestamps();
    $table->unique(['company_id','year']);
});
```

## Durumlar

| Durum | Anlam |
|---|---|
| `active` | Çalışılan dönem, kayıt girilebilir |
| `closed` | Kapatıldı, salt okunur, rapor alınabilir |
| `archived` | Veritabanı ayrılmış/arşivlenmiş, açılması gerekir |

## Kurallar

- Dönem oluşturma: veritabanı yaratılır, migration çalıştırılır,
  `periods` satırı eklenir — hepsi tek işlemde
- Kapalı döneme kayıt girilemez (veritabanı seviyesinde salt okunur
  kullanıcıyla da desteklenebilir)
- Devir yapılmadan yeni dönemde açılış bakiyesi olmaz;
  `carried_at` boşsa arayüz uyarır
- Aynı şirket + yıl ikinci kez oluşturulamaz

## Dönem oluşturma

```php
final class CreatePeriod
{
    public function handle(Company $company, int $year): Period
    {
        $dbName = "{$company->db_prefix}_{$year}";

        DB::connection('master')->statement("CREATE DATABASE \"{$dbName}\"");

        $period = Period::on('master')->create([
            'company_id'    => $company->id,
            'year'          => $year,
            'database_name' => $dbName,
            'starts_on'     => "{$year}-01-01",
            'ends_on'       => "{$year}-12-31",
            'status'        => 'active',
        ]);

        PeriodContext::use($company->id, $year);
        Artisan::call('migrate', ['--database' => 'period', '--path' => 'database/migrations/period', '--force' => true]);

        return $period;
    }
}
```

**Not:** `CREATE DATABASE` transaction içinde çalışmaz. Hata durumunda
oluşan veritabanı elle silinmelidir; action bunu `try/catch` ile ele alır.

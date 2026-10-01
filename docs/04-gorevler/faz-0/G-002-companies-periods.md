# G-002 — companies ve periods (master)

## Amaç
Şirket kartı ve dönem kaydı. Bağlantı yönetimi bu iki tabloya dayanır.

## Önkoşul
G-001

## Dokunulacak dosyalar
- `database/migrations/master/xxxx_create_companies_table.php`
- `database/migrations/master/xxxx_create_periods_table.php`
- `app/Models/Company.php`, `app/Models/Period.php`
- `app/Actions/Periods/CreatePeriod.php`
- `database/seeders/CompanySeeder.php`

## Şemalar
`docs/01-veri-modeli/01-companies.md` ve `01b-periods.md` içindeki
şemaları **birebir** uygula. İkisi de `Schema::connection('master')`.

## CreatePeriod action

```php
final class CreatePeriod
{
    public function handle(Company $company, int $year): Period
    {
        $dbName = "{$company->db_prefix}_{$year}";

        if (Period::on('master')->where('database_name', $dbName)->exists()) {
            throw new \RuntimeException("{$dbName} zaten var.");
        }

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
        Artisan::call('migrate', [
            '--database' => 'period',
            '--path'     => 'database/migrations/period',
            '--force'    => true,
        ]);

        return $period;
    }
}
```

**Dikkat:** `CREATE DATABASE` transaction içinde çalışmaz. Migration
başarısız olursa oluşan veritabanı elle silinmelidir; action bunu
`try/catch` ile ele alır ve kullanıcıya bildirir.

## Model kuralları
- `Company` ve `Period` → `protected $connection = 'master'`
  (bunlar `MasterModel`'den türemez, çünkü `company_id` taşımazlar)
- `db_prefix` kayıt sonrası salt okunur; yalnızca harf, rakam, alt çizgi
- `Period::databaseName()` → `{db_prefix}_{year}`

## Seed
| code | name | db_prefix | dönemler |
|---|---|---|---|
| ABCHOLDING | ABC Holding | ABCHolding | 2026 |
| XYZLTD | XYZ Ltd. Şti. | XYZltd | 2026 |

## Kabul ölçütü
```bash
php artisan migrate --database=master --path=database/migrations/master
php artisan db:seed
psql -l | grep -E "ABCHolding_2026|XYZltd_2026"   # ikisi de var
```
- Aynı şirket+yıl ikinci kez oluşturulamıyor
- `db_prefix` değiştirilmeye çalışılınca reddediliyor

## İstem
> companies ve periods tabloları için master migration'larını, Company ve
> Period modellerini, CreatePeriod action'ını ve CompanySeeder'ı yaz.
> Şemaları belirtilen dosyalardan birebir al. CreatePeriod veritabanını
> oluşturup migration çalıştırsın, hata durumunu try/catch ile ele alsın.

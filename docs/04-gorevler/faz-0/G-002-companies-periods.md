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


## Şema / Kod

Bu görevde aşağıdaki mevcut kod/şema örnekleri normatiftir. Yeni tablo gerekmiyorsa migration ekleme; mevcut mimari sözleşmeyi bozacak ek şema uydurma.

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

        PeriodContext::use($company->id, $period->id);
        Artisan::call('migrate', [
            '--database' => 'period',
            '--path'     => 'database/migrations/period',
            '--force'    => true,
        ]);

        return $period;
    }
}
```

**Dikkat:** `CREATE DATABASE` transaction içinde çalışmaz. Migration başarısız olursa action oluşturduğu `periods` kaydını ve henüz işletme verisi almamış hedef DB'yi `try/catch` telafi adımıyla temizler; yarım period kaydı bırakmaz.

## Model kuralları
- `Company` ve `Period` → `MasterModel`'den türer; ikisi de `master` bağlantısını kullanır. `periods.company_id` Master içi geçerli FK'dir ve global scope amacıyla kullanılmaz.
- `db_prefix` kayıt sonrası salt okunur; yalnızca harf, rakam, alt çizgi
- `Period::databaseName()` → `{db_prefix}_{year}`

## Seed
| code | name | db_prefix | dönemler |
|---|---|---|---|
| ABCHOLDING | ABC Holding | ABCHolding | 2026 |
| XYZLTD | XYZ Ltd. Şti. | XYZltd | 2026 |


## Kurallar

**Faz bağlamı:** Faz 0 — altyapı; sonraki fazların sözleşmesini bozmamalı.

### Bu göreve özel kanonik notlar
- companies ve periods Master DB'dedir.
- Period satırı company_id, year, database_name, status bilgilerini taşır; business kartı tutmaz.


### Uygulama ayrıntıları
- `companies` ve `periods` yalnız Master DB'dedir.
- `periods.company_id` Master içi geçerli FK'dir; period işletme tablolarındaki yasak `company_id` ile karıştırılmaz.
- `company_id + year` benzersizdir; `database_name` fiziksel period DB adını tutar.
- Period durumu ve devir metadata'sı Master'da tutulur; kart/operasyon verisi bu tablolara taşınmaz.

## Kabul ölçütü
```bash
php artisan migrate --database=master --path=database/migrations/master
php artisan db:seed
psql -l | grep -E "ABCHolding_2026|XYZltd_2026"   # ikisi de var
```
- Aynı şirket+yıl ikinci kez oluşturulamıyor
- Company/Period düzenlemede stale `version` reddediliyor
- `db_prefix` değiştirilmeye çalışılınca reddediliyor


## İstem
> companies ve periods tabloları için master migration'larını, Company ve
> Period modellerini, CreatePeriod action'ını ve CompanySeeder'ı yaz.
> Şemaları belirtilen dosyalardan birebir al. CreatePeriod veritabanını
> oluşturup migration çalıştırsın, hata durumunu try/catch/telafi temizliği ile ele alsın. Company ve Period düzenlemelerini `version` optimistic lock ile koru.

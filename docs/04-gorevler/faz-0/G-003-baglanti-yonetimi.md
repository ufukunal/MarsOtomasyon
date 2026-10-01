# G-003 — Bağlantı yönetimi (master + dönem)

## Amaç
Sistemin kalbi. Kullanıcı şirket ve dönem seçer, uygulama o veritabanına
bağlanır. **Bu görev yanlış yapılırsa hiçbir şey çalışmaz.**

## Önkoşul
G-002 (companies + periods)

## Dokunulacak dosyalar
- `config/database.php`
- `app/Support/Period/PeriodContext.php`
- `app/Models/MasterModel.php`
- `app/Models/PeriodModel.php`
- `app/Http/Middleware/SetActivePeriod.php`
- `bootstrap/app.php`

## config/database.php

```php
'master' => [
    'driver'   => 'pgsql',
    'host'     => env('DB_HOST', '127.0.0.1'),
    'database' => env('DB_MASTER_DATABASE', 'MarsProject_Master'),
    'username' => env('DB_USERNAME'),
    'password' => env('DB_PASSWORD'),
    'charset'  => 'utf8',
    'search_path' => 'public',
],

'period' => [
    'driver'   => 'pgsql',
    'host'     => env('DB_HOST', '127.0.0.1'),
    'database' => null,                 // çalışma anında doldurulur
    'username' => env('DB_USERNAME'),
    'password' => env('DB_PASSWORD'),
    'charset'  => 'utf8',
    'search_path' => 'public',
],
```

`'default' => 'master'`

## PeriodContext

```php
namespace App\Support\Period;

final class PeriodContext
{
    public static function use(int $companyId, int $year): Period
    {
        $period = Period::on('master')
            ->where('company_id', $companyId)
            ->where('year', $year)
            ->firstOrFail();

        config(['database.connections.period.database' => $period->database_name]);
        DB::purge('period');
        DB::reconnect('period');

        session(['active_company_id' => $companyId, 'active_year' => $year]);

        return $period;
    }

    public static function companyId(): ?int { return session('active_company_id'); }
    public static function year(): ?int      { return session('active_year'); }

    public static function ensure(): void
    {
        if (! self::companyId() || ! self::year()) {
            throw new NoActivePeriodException('Şirket ve dönem seçilmedi.');
        }
    }
}
```

## Model temel sınıfları

```php
abstract class MasterModel extends Model
{
    protected $connection = 'master';
    // global scope YOK — master'da yalnız şirket üstü tablolar var
}

abstract class PeriodModel extends Model
{
    protected $connection = 'period';
    // company_id YOK, global scope YOK — izolasyon fiziksel
}
```

**Kartlar dahil her iş tablosu `PeriodModel`'den türer.**

## Middleware

`SetActivePeriod`:
1. Giriş yapılmamışsa devam
2. Session'da şirket/dönem yoksa: `users.last_company_id` ve son aktif
   dönem; yoksa seçim ekranına yönlendir
3. Kullanıcı o şirkete `company_user` üzerinden bağlı değilse 403
4. Dönem `archived` ise uyarı ve seçim ekranı
5. `PeriodContext::use($companyId, $year)`

`web` grubuna, `auth`'tan **sonra** eklenir.

## Migration klasörleri

```
database/migrations/master/    ← master tabloları
database/migrations/period/    ← dönem tabloları
```

Komutlar:
```bash
php artisan migrate --database=master --path=database/migrations/master
php artisan migrate --database=period --path=database/migrations/period
```

## Kuyruk işleri

Kuyrukta session yoktur. İş `company_id` ve `year` taşır:

```php
public function handle(): void
{
    PeriodContext::use($this->companyId, $this->year);
    // ...
}
```

## Kabul ölçütü
- İki şirket, iki dönem oluşturuluyor (4 veritabanı)
- Şirket/dönem değişince sorgular doğru veritabanına gidiyor
- Dönem modellerinde `company_id` kolonu **yok**
- Kart tabloları (contacts, products) dönem veritabanında
- Kuyruk işi doğru veritabanına bağlanıyor
- Dönem seçilmeden dönem modeline erişim `NoActivePeriodException`

## İstem
> config/database.php'ye master ve period bağlantılarını ekle.
> PeriodContext, MasterModel, PeriodModel ve SetActivePeriod
> middleware'ini yukarıdaki kodla birebir yaz. Migration klasörlerini
> master ve period olarak ayır. DB::purge ve DB::reconnect satırlarını
> atlama. Başka dosyaya dokunma.

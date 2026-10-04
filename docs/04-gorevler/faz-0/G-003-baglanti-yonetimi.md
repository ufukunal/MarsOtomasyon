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


## Şema / Kod

Bu görevde aşağıdaki mevcut kod/şema örnekleri normatiftir. Yeni tablo gerekmiyorsa migration ekleme; mevcut mimari sözleşmeyi bozacak ek şema uydurma.

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
    public static function use(int $companyId, int $periodId): Period
    {
        $period = Period::on('master')
            ->whereKey($periodId)
            ->where('company_id', $companyId)
            ->firstOrFail();

        config(['database.connections.period.database' => $period->database_name]);
        DB::purge('period');
        DB::reconnect('period');

        session([
            'active_company_id' => $companyId,
            'active_period_id' => $period->id,
            'active_year' => $period->year,
        ]);

        return $period;
    }

    public static function companyId(): ?int { return session('active_company_id'); }
    public static function periodId(): ?int  { return session('active_period_id'); }
    public static function year(): ?int      { return session('active_year'); }

    public static function ensure(): void
    {
        if (! self::companyId() || ! self::periodId()) {
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
3. Seçilen `period_id` kaydının aynı `company_id`'ye ait olduğunu doğrula
4. Kullanıcı o şirkete `company_user` üzerinden bağlı değilse 403; seçilen dönem için `period_user_access` yoksa 403
5. Dönem `archived` ise uyarı ve seçim ekranı
6. **Yetki kontrollerinden sonra** `PeriodContext::use($companyId, $periodId)`

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

Kuyrukta session yoktur. İş `company_id` ve `period_id` taşır:

```php
public function handle(): void
{
    PeriodContext::use($this->companyId, $this->periodId);
    // ...
}
```


## Kurallar

**Faz bağlamı:** Faz 0 — altyapı; sonraki fazların sözleşmesini bozmamalı.

### Bu göreve özel kanonik notlar
- Kalıcı bağlantılar yalnız master ve period; period_source yalnız kopyalama sırasında geçicidir.
- PeriodContext kurulmadan önce company_user + period_user_access kontrol edilir.
- Kartlar period DB'dedir; ayrı company connection oluşturma.


### Uygulama ayrıntıları
- Kalıcı bağlantılar `master` ve aktif `period`dur; `period_source` yalnız şirketler arası kopyalamada geçici kullanılır.
- `PeriodContext` kurulmadan önce `company_user` ve `period_user_access` doğrulanır.
- Period DB adı Master `periods.database_name` kaydından gelir; kullanıcıdan serbest DB adı alınmaz.
- Queue işleri session'a güvenmez; şirket+dönem bağlamını payload ile taşır ve handle başında context kurar.

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

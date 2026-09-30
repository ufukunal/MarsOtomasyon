# G-003 — Şirket bağlamı ve global scope

## Amaç
Şirket izolasyonunun kalbi. Her sorgu aktif şirketle filtrelenir, her yeni
kayıt aktif şirketin `company_id`'sini alır.

**Bu görev yanlış yapılırsa sistem baştan sona veri sızdırır.**

## Önkoşul
G-002

## Dokunulacak dosyalar
- `app/Support/Company/CompanyContext.php`
- `app/Support/Company/BelongsToCompany.php`
- `app/Http/Middleware/SetActiveCompany.php`
- `bootstrap/app.php` (middleware kaydı)

## CompanyContext

```php
namespace App\Support\Company;

final class CompanyContext
{
    private static ?int $companyId = null;

    public static function set(?int $id): void { self::$companyId = $id; }

    public static function id(): ?int
    {
        return self::$companyId ?? session('active_company_id');
    }

    public static function forget(): void { self::$companyId = null; }
}
```

## BelongsToCompany trait

```php
namespace App\Support\Company;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Company;

trait BelongsToCompany
{
    protected static function bootBelongsToCompany(): void
    {
        static::addGlobalScope('company', function (Builder $q) {
            if ($id = CompanyContext::id()) {
                $q->where($q->getModel()->getTable().'.company_id', $id);
            }
        });

        static::creating(function ($model) {
            if (empty($model->company_id)) {
                $model->company_id = CompanyContext::id();
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
```

## Middleware

`SetActiveCompany`:
1. Kullanıcı giriş yapmamışsa devam et
2. `session('active_company_id')` boşsa `auth()->user()->last_company_id`
   veya kullanıcının ilk şirketini ata
3. Kullanıcı o şirkete `company_user` üzerinden bağlı değilse 403
4. `CompanyContext::set($id)`

Middleware `web` grubuna, `auth`'tan **sonra** eklenir.

## Kuyruk işleri
Kuyrukta session yoktur. İş kuyruğa atılırken `company_id` taşınır,
`handle()` başında `CompanyContext::set($this->companyId)` çağrılır.

## Kabul ölçütü
Pest testi (G-011'de genişletilecek):
- Şirket A'da oluşturulan kayıt, şirket B bağlamında **görünmemeli**
- `company_id` elle verilmeden otomatik dolmalı

## İstem
> CompanyContext sınıfını, BelongsToCompany trait'ini ve SetActiveCompany
> middleware'ini yukarıdaki kodla birebir yaz. Middleware'i bootstrap/app.php
> içinde web grubuna auth'tan sonra ekle. Başka dosyaya dokunma.

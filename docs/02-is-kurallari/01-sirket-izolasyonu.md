# Şirket izolasyonu

## Kural

Şirketler **tam izoledir**. Bir şirkette çalışan kullanıcı, diğer şirketin
hiçbir verisini göremez: listede çıkmaz, aramada bulunmaz, doğrudan URL ile
erişilemez.

Tek istisna: `company_links` tablosunda izin tanımlıysa, **kopyalama ekranında**
kaynak şirketin cari veya ürün kartları listelenir. Kopyalama dışında yine görünmez.

## Uygulama

`BelongsToCompany` trait'i her iş modeline eklenir:

```php
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

## Aktif şirket

- `session('active_company_id')` içinde tutulur
- `CompanyContext::id()` ile okunur, başka yerden okunmaz
- Değiştirme: kullanıcı yalnızca `company_user` tablosunda bağlı olduğu
  şirketleri seçebilir
- Şirket değişince `users.last_company_id` güncellenir
- Kuyruk işlerinde session yoktur: iş kuyruğa atılırken `company_id`
  taşınır ve işçi `CompanyContext::set($id)` ile başlar

## Sızıntı riskleri — dikkat

1. **Trait eklemeyi unutmak.** Her yeni model için izolasyon testi zorunlu.
2. **Ham SQL / `DB::table()`.** Global scope çalışmaz; elle `where company_id`
   eklenmeli. Mümkünse ham sorgu kullanılmaz.
3. **`withoutGlobalScopes()`.** Yalnızca kopyalama ekranında ve yalnızca
   izin doğrulandıktan sonra kullanılır.
4. **Yabancı anahtar doğrulaması.** Bir belgede seçilen cari, aktif şirkete
   ait olmalı; `exists` kuralı `company_id` ile birlikte yazılır.

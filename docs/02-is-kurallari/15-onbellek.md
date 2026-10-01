# Önbellek

Sürücü Valkey (Redis uyumlu). Önbellek, oturum ve kuyruk aynı sunucuyu
kullanır ama **ayrı veritabanı numaralarında** durur:

```
REDIS_CACHE_DB=1
REDIS_SESSION_DB=2
REDIS_QUEUE_DB=3
```

Aynı numarada tutulursa `cache:clear` kuyruktaki işleri de siler.

## EN KRİTİK KURAL — anahtar dönem taşır

Master/dönem mimarisinde önbellek anahtarı **şirket ve dönem içermezse
veri sızar.** ABC Holding'in 2026 stoğu, XYZ Ltd.'nin ekranında görünür.

Bu, global scope'un korumadığı tek yerdir: önbellek Eloquent'in altından
geçer.

```php
final class CacheKey
{
    public static function period(string $key): string
    {
        return sprintf('c%d:y%d:%s',
            PeriodContext::companyId(),
            PeriodContext::year(),
            $key
        );
    }

    public static function master(string $key): string
    {
        return sprintf('c%d:%s', PeriodContext::companyId(), $key);
    }

    public static function global(string $key): string
    {
        return "g:{$key}";
    }
}
```

**Kural:** `Cache::get()` / `Cache::put()` **doğrudan çağrılmaz.**
Anahtar her zaman `CacheKey` üzerinden üretilir. Kod incelemesinde
çıplak `Cache::` çağrısı hata sayılır.

| Veri türü | Anahtar | Örnek |
|---|---|---|
| Dönem verisi (stok, belge, bakiye) | `CacheKey::period()` | `c1:y2026:stock_summary` |
| Master kart verisi (cari, ürün, fiyat) | `CacheKey::master()` | `c1:product_list_v3` |
| Sistem geneli (döviz kuru, ayar) | `CacheKey::global()` | `g:exchange_rates:2026-09-30` |

## Ne önbelleğe alınır

| Veri | Süre | Neden |
|---|---|---|
| Menü ağacı (yetkiye göre) | 1 saat | Her sayfada üretiliyor, nadiren değişiyor |
| Kullanıcı izinleri | 15 dk | Her yetki kontrolünde sorgu olmasın |
| Fiyat listesi satırları | 30 dk | Belge satırında sürekli okunuyor |
| Döviz kurları | gün sonuna kadar | Gün içinde değişmiyor |
| Kategori / marka / birim listeleri | 6 saat | Açılır listelerde sürekli |
| Ana sayfa göstergeleri | 5 dk | Ağır sorgular |
| Rapor sonuçları (çok dönemli) | 10 dk | Birden çok veritabanı taranıyor |

## Ne önbelleğe ALINMAZ

**Stok bakiyesi, cari bakiyesi, belge durumu, numara sayacı.**

Bunlar işlem anında doğru olmak zorundadır. Önbellekten okunan bir stok
bakiyesiyle satış yapılırsa negatif stok ya da çift satış olur.

`stock_balances` zaten türetilmiş bir özet tablodur; ikinci bir önbellek
katmanı gereksiz risk.

## Geçersiz kılma

Zaman aşımına güvenme; veri değişince **hemen** temizle.

```php
// Ürün kaydedilince
Cache::forget(CacheKey::master('product_list_v3'));
Cache::forget(CacheKey::master("product:{$product->id}"));

// Fiyat listesi değişince
Cache::tags(['prices'])->flush();     // Valkey etiket destekler
```

Model observer'larında yapılır, ekran kodunda değil.

## Dönem değişiminde

`PeriodContext::use()` çağrıldığında **önbellek temizlenmez** — anahtarlar
zaten dönem taşıdığı için karışma olmaz. Temizlemek, diğer dönemin
önbelleğini de boşuna atmak olur.

## Kuyruk işlerinde

Kuyrukta `PeriodContext` iş başında kurulur; `CacheKey` ondan sonra
doğru çalışır. **İş başında bağlam kurulmadan `CacheKey` çağrılırsa
`c0:y0:` gibi bozuk anahtar üretir** — bu sessiz bir hatadır, dikkat.

## Yetki önbelleği

spatie/laravel-permission kendi önbelleğini tutar ve **teams (şirket)
bazlıdır**. Rol veya izin değişince `php artisan permission:cache-reset`
çalıştırılır; uygulama içinde rol atanınca observer bunu yapar.

## Ne zaman önbellek KOYMA

Ölçmeden önbellek ekleme. 5-30 kullanıcılı bir sistemde çoğu sorgu
zaten hızlıdır; erken önbellek, bayat veri ve zor bulunan hata demektir.

Önce yavaş olduğunu ölç, sonra önbelleğe al.

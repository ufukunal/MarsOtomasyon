# G-016 — Önbellek altyapısı

## Amaç
Valkey bağlantısı ve **dönem bazlı anahtar üretimi**. Anahtar dönem
taşımazsa şirketler arası veri sızar.

## Önkoşul
G-003 (bağlantı yönetimi)

## Dokunulacak dosyalar
- `config/database.php` (redis bölümü)
- `app/Support/Cache/CacheKey.php`
- `.env.example`


## Şema / Kod

Bu görev yeni tablo gerektirmiyorsa migration ekleme. Mevcut kod örnekleri ve aşağıdaki mimari sözleşme normatiftir.

## Ayrı veritabanı numaraları

```
REDIS_CACHE_DB=1
REDIS_SESSION_DB=2
REDIS_QUEUE_DB=3
```

Aynı numarada tutulursa `cache:clear` kuyruğu da siler.

## CacheKey

```php
namespace App\Support\Cache;

use App\Support\Period\PeriodContext;

final class CacheKey
{
    public static function period(string $key): string
    {
        $c = PeriodContext::companyId();
        $y = PeriodContext::year();

        if (! $c || ! $y) {
            throw new \RuntimeException('Dönem bağlamı kurulmadan önbellek anahtarı üretilemez.');
        }

        return "c{$c}:y{$y}:{$key}";
    }

    public static function master(string $key): string
    {
        $c = PeriodContext::companyId();

        if (! $c) {
            throw new \RuntimeException('Şirket bağlamı kurulmadan önbellek anahtarı üretilemez.');
        }

        return "c{$c}:{$key}";
    }

    public static function global(string $key): string
    {
        return "g:{$key}";
    }
}
```

**Bağlam yoksa istisna fırlatılır** — sessizce `c0:y0:` üretmek, kuyruk
işlerinde fark edilmeyen sızıntı demektir.

## Kural

`Cache::get()` ve `Cache::put()` **doğrudan çağrılmaz.** Anahtar her
zaman `CacheKey` üzerinden üretilir. Larastan kuralı veya kod incelemesi
çıplak `Cache::` çağrısını yakalar.

## Önbelleğe ALINMAYACAKLAR

Stok bakiyesi, cari bakiyesi, belge durumu, numara sayacı.
Bunlar işlem anında doğru olmak zorundadır.


## Kurallar

**Faz bağlamı:** Faz 0 — altyapı.

### Göreve özel kararlar
- Kartlar period DB'dedir; ürün/cari/fiyat için master cache anahtarı üretme.
- Stok/cari/number_series cache edilmez.
- Bağlam yoksa CacheKey period anahtarı üretmez.


### Uygulama ayrıntıları
- Period cache anahtarı şirket+yıl bağlamı taşır; bağlam yoksa anahtar üretmek yerine hata verilir.
- Ürün/cari/fiyat listesi kartları period DB'de olduğundan Master cache namespace kullanılmaz.
- Stok bakiyesi, cari bakiyesi, belge durumu, rezervasyon anlık miktarı ve number_series cache edilmez.
- Cache invalidation ilgili kart/ayar değişikliğinde açıkça tetiklenir; TTL tek doğruluk mekanizması değildir.

## Kabul ölçütü
- Şirket A'da üretilen anahtar `c1:y2026:...` biçiminde
- Şirket B'ye geçince aynı `$key` farklı anahtar üretiyor
- Bağlam kurulmadan `CacheKey::period()` istisna fırlatıyor
- Cache, session ve queue ayrı Redis veritabanlarında
- Kuyruk işi içinde `CacheKey` doğru çalışıyor (bağlam kurulduktan sonra)


## İstem
> config/database.php'de redis için cache, session ve queue'yu ayrı
> veritabanı numaralarına ayır. CacheKey sınıfını yukarıdaki kodla
> birebir yaz — bağlam yoksa istisna fırlatsın. .env.example'ı güncelle.
> Başka yere önbellek ekleme.

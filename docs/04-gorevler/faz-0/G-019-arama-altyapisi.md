# G-019 — Türkçe arama altyapısı

## Amaç
PostgreSQL varsayılan ayarlarla Türkçe aramada yanlış sonuç verir.
`İSTANBUL` küçültülünce `istanbul` ile eşleşmez; `oztas` yazan `Öztaş`
bulamaz.

**Bu, sistemin ilk gününde ortaya çıkar.**

## Önkoşul
G-003

## Dokunulacak dosyalar
- `app/Support/Search/SearchNormalizer.php`
- `app/Support/Search/HasSearchIndex.php` (trait)
- `database/migrations/master/xxxx_enable_pg_trgm.php`


## Şema / Kod

Bu görev yeni tablo gerektirmiyorsa migration ekleme. Mevcut kod örnekleri ve aşağıdaki mimari sözleşme normatiftir.

## SearchNormalizer

`docs/02-is-kurallari/21-arama-ve-turkce.md` içindeki sınıfı birebir yaz.
Türkçe harfleri ASCII karşılığına çevirir, küçültür, noktalamayı atar.

## HasSearchIndex trait

```php
trait HasSearchIndex
{
    abstract public function searchableFields(): array;

    protected static function bootHasSearchIndex(): void
    {
        static::saving(function ($model) {
            $parts = array_map(fn ($f) => $model->{$f}, $model->searchableFields());
            $model->search_index = SearchNormalizer::make(implode(' ', $parts));
        });
    }

    public function scopeSearch(Builder $q, ?string $term): Builder
    {
        if (blank($term)) return $q;

        foreach (explode(' ', SearchNormalizer::make($term)) as $t) {
            $q->where('search_index', 'like', "%{$t}%");
        }
        return $q;
    }
}
```

**Her terim geçmeli** (AND), herhangi biri değil. "avize park ankara"
üç terimi de içeren kaydı bulur.

## Trigram indeksi

```sql
CREATE EXTENSION IF NOT EXISTS pg_trgm;

CREATE INDEX contacts_search_trgm
  ON contacts USING gin (search_index gin_trgm_ops);
```

`LIKE '%...%'` normal indeks kullanmaz; trigram olmadan yüz bin kayıtta
arama saniyelere çıkar.

## Türkçe sıralama

```sql
ALTER TABLE contacts
  ALTER COLUMN title TYPE varchar(255) COLLATE "tr-TR-x-icu";
```

Yoksa `Ç` harfi `Z`'den sonra sıralanır.

## Barkod istisnası

Barkod **normalize edilmez**, tam eşleşmeyle aranır. Barkod okuyucu
benzerlik değil kesinlik ister.

## Uygulanacak tablolar
`contacts`, `products`, `documents`, `locations` — her birine
`search_index` kolonu ve trigram indeksi.


## Kurallar

**Faz bağlamı:** Faz 0 — altyapı.

### Göreve özel kararlar
- Kart search_index alanları period DB'dedir.
- Türkçe normalize + pg_trgm gerçek PostgreSQL'de test edilir.


### Uygulama ayrıntıları
- Aranabilir kart alanları normalize edilmiş `search_index` üretir.
- Türkçe büyük/küçük harf ve aksan normalizasyonu uygulama tarafında tek helper üzerinden yapılır.
- PostgreSQL `pg_trgm` indeksi gerçek period tablolarında oluşturulur; SQLite alternatifi yoktur.
- Arama query'si aktif period connection'ını korur ve başka şirket DB'sine geçmez.

## Kabul ölçütü
- `oztas` → `Öztaş Aydınlatma` buluyor
- `ÖZTAŞ` → aynı kaydı buluyor
- `aydinlatma` → buluyor
- `İstanbul` araması `Istanbul` kaydını buluyor
- Çok kelimeli arama AND mantığıyla çalışıyor
- 100.000 kayıtta arama 300 ms altında
- Barkod tam eşleşmeyle bulunuyor, kısmi eşleşme dönmüyor
- Liste sıralamasında `Ç` doğru yerde


## İstem
> SearchNormalizer sınıfını, HasSearchIndex trait'ini ve pg_trgm
> eklentisini kuran migration'ı yaz. contacts, products, documents ve
> locations tablolarına search_index kolonu ve trigram indeksi ekle.
> Türkçe collation'ı title/name kolonlarına uygula. Barkodu normalize
> ETME.

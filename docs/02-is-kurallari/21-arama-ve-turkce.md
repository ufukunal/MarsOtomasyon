# Arama ve Türkçe karakter

**Bu, sistemin ilk gününde canını yakacak konudur.** PostgreSQL
varsayılan ayarlarla Türkçe aramada yanlış sonuç verir.

## Sorun

PostgreSQL'in `lower()` fonksiyonu varsayılan yerelde **`İ` harfini
doğru küçültmez**. `İSTANBUL` → `i̇stanbul` (birleşik nokta) olur ve
`istanbul` ile eşleşmez.

Ayrıca kullanıcı `oztas` yazıp `Öztaş` bulmak ister; `sisli` yazıp
`Şişli` bulmak ister.

## Çözüm: normalize edilmiş arama kolonu

Aranacak her metin alanı için ikinci bir **normalize kolon** tutulur.

```php
$table->string('title');
$table->string('title_search')->nullable()->index();   // normalize
```

Normalizasyon PHP tarafında, tek bir yerden:

```php
final class SearchNormalizer
{
    private const MAP = [
        'ı'=>'i','İ'=>'i','I'=>'i','i'=>'i',
        'ş'=>'s','Ş'=>'s','ğ'=>'g','Ğ'=>'g',
        'ü'=>'u','Ü'=>'u','ö'=>'o','Ö'=>'o',
        'ç'=>'c','Ç'=>'c','â'=>'a','Â'=>'a','î'=>'i','û'=>'u',
    ];

    public static function make(?string $value): string
    {
        if ($value === null) return '';
        $v = strtr($value, self::MAP);
        $v = mb_strtolower($v, 'UTF-8');
        $v = preg_replace('/[^a-z0-9]+/u', ' ', $v);   // noktalama at
        return trim(preg_replace('/\s+/', ' ', $v));
    }
}
```

Model observer'ında doldurulur:

```php
static::saving(function (Contact $c) {
    $c->title_search = SearchNormalizer::make(
        $c->title.' '.$c->code.' '.$c->tax_number.' '.$c->phone
    );
});
```

**Birden çok alan tek normalize kolonda birleştirilir** — böylece tek
`LIKE` ile kod, unvan, vergi no ve telefonda birden aranır.

Arama:

```php
$terms = explode(' ', SearchNormalizer::make($input));

$query->where(function ($q) use ($terms) {
    foreach ($terms as $t) {
        $q->where('title_search', 'like', "%{$t}%");    // HER terim geçmeli
    }
});
```

## İndeks

`LIKE '%...%'` normal indeks kullanmaz. PostgreSQL'de **trigram indeksi**
gerekir:

```sql
CREATE EXTENSION IF NOT EXISTS pg_trgm;
CREATE INDEX contacts_title_search_trgm
  ON contacts USING gin (title_search gin_trgm_ops);
```

Bu, yüz binlerce kayıtta bile `%kelime%` aramasını hızlı tutar.

## Sıralama

Türkçe alfabetik sıralama için kolon bazında collation:

```sql
ALTER TABLE contacts
  ALTER COLUMN title TYPE varchar(255) COLLATE "tr-TR-x-icu";
```

Yoksa `Ç` harfi `Z`'den sonra sıralanır ve liste yanlış görünür.

## Hangi tablolarda normalize kolon

| Tablo | Normalize edilen alanlar |
|---|---|
| `contacts` | kod, unvan, vergi no, telefon, e-posta, il |
| `products` | kod, ad, barkod, marka, kategori |
| `documents` | numara, cari unvanı, not |
| `locations` | kod, ad |

## Barkod aramanın özel durumu

Barkod okuyucu tam eşleşme ister, benzerlik değil. Barkod alanı
**normalize edilmez**, ayrı ve tam eşleşmeli aranır:

```php
if ($exact = Product::where('barcode', $input)->first()) {
    return $exact;         // doğrudan seç, liste gösterme
}
```

Bu, `02-is-kurallari` içindeki lookup bileşeni davranışının temelidir.

## Test

```php
it('turkce karakter duyarsiz arar', function () {
    Contact::factory()->create(['title' => 'Öztaş Aydınlatma']);

    expect(ara('oztas'))->toHaveCount(1);
    expect(ara('ÖZTAŞ'))->toHaveCount(1);
    expect(ara('aydinlatma'))->toHaveCount(1);
    expect(ara('İstanbul'))->toHaveCount(0);
});
```

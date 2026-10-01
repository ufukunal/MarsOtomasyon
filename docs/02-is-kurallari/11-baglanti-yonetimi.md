# Bağlantı yönetimi

## Üç bağlantı

| Bağlantı | Veritabanı | İçerik |
|---|---|---|
| `master` | `MarsProject_Master` | Şirketler, kullanıcılar, yetkiler, sistem |
| `company` | `{ŞirketKodu}` | Kartlar ve tanımlar |
| `period` | `{ŞirketKodu}_{Yıl}` | Hareketler ve belgeler |

`company` ve `period` çalışma anında doldurulur.

## Model bağlantıları — kural

Her model `$connection` özelliğini **açıkça** belirtir. Belirtmeyen model
varsayılana düşer ve yanlış veritabanına yazar.

```php
class Contact extends Model       { protected $connection = 'company'; }
class Document extends Model      { protected $connection = 'period'; }
```

**Yeni model yazarken ilk satır bu olmalı.** Unutulması, verinin yanlış
veritabanına gitmesi demektir ve sessizce olur.

## Middleware

`SetDatabaseContext` her istekte çalışır:

1. Oturumdan aktif şirket ve yıl okunur
2. Kullanıcının o şirkete erişimi var mı (`company_user`)
3. O şirket-yıl veritabanı kayıtlı ve açık mı (`company_databases`)
4. `DatabaseContext::use($company, $year)` çağrılır

Erişim yoksa 403; veritabanı yoksa şirket/dönem seçim ekranına yönlendirilir.

## Kuyruk işleri

Kuyrukta oturum yoktur. Her iş `company_id` ve `year` taşır:

```php
public function handle(): void
{
    DatabaseContext::use(Company::find($this->companyId), $this->year);
    // ...
}
```

**Bunu unutan iş yanlış veritabanına yazar.** Base job sınıfı bunu
zorunlu kılar.

## İlişki kısıtı — önemli

Farklı bağlantılardaki tablolar arasında **veritabanı seviyesinde
yabancı anahtar kurulamaz.**

Örnek: `documents.contact_id` → `contacts.id` ilişkisi, belge `period`
veritabanında, cari `company` veritabanında olduğu için foreign key
constraint ile korunamaz.

Sonuç:
- İlişki **uygulama seviyesinde** doğrulanır (kayıt öncesi `exists` kontrolü)
- Eloquent `belongsTo` çalışır (ayrı sorgu atar), `join` çalışmaz
- Liste ekranlarında cari adı göstermek için **denormalizasyon** gerekir:
  `documents` tablosunda `contact_code` ve `contact_title` kolonları
  belge kesinleşirken kopyalanır

Bu denormalizasyon ayrıca doğrudur: belge kesinleştiği andaki cari unvanını
saklamak, cari sonradan adını değiştirse bile eski belgenin doğru
görünmesini sağlar.

## Çapraz dönem sorgu

```php
$results = MultiPeriodQuery::for($company, [2025, 2026])
    ->run(fn () => Document::where('document_type','sales_invoice')
        ->whereBetween('date', [$from, $to])
        ->sum('grand_total'));
```

Her yıl için sırayla bağlanır, sonuçları birleştirir. Yıllık karşılaştırma
raporları bunu kullanır.

**Performans notu:** çok yıllı rapor N sorgu demektir. Sık kullanılan
karşılaştırmalar için `MarsProject_Master` içinde bir özet tablo
düşünülebilir (dönem kapanışında doldurulur).

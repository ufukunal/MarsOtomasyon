# G-0b2 — Tablo bileşeni

## Amaç
Tüm liste ekranlarının dayanacağı tek bileşen. Bir kez yazılır, 100+ ekranda
kullanılır.

## Önkoşul
G-0b1

## Yetenekler

| Yetenek | Davranış |
|---|---|
| Arama | Çok kelimeli, Türkçe karakter duyarsız, seçili kolonlarda |
| Sıralama | Kolon başlığına tıklayarak, çift yönlü |
| Filtre | Açılır panel, kolon bazında tanımlanır |
| Sayfalama | 25/50/100, sunucu tarafında |
| Kolon gizleme | Kullanıcı tercihi kalıcı |
| Satır seçimi | Tekli/çoklu, tümünü seç görünen satırlarda |
| Toplu işlem | Seçili satırlara eylem |
| Dışa aktarma | Excel/CSV, **filtrelenmiş** satırlar, BOM + noktalı virgül |
| Satır eylemi | Düzenle, sil, özel eylemler |
| Boş durum | "Kayıt bulunamadı" + varsa "Yeni ekle" |

## Kullanım hedefi

```php
class ContactList extends DataTableComponent
{
    public string $model = Contact::class;

    public function columns(): array
    {
        return [
            Column::make('code', 'Kod')->searchable()->sortable(),
            Column::make('title', 'Unvan')->searchable()->sortable(),
            Column::make('balance', 'Bakiye')->money()->alignEnd(),
        ];
    }

    public function filters(): array
    {
        return [ SelectFilter::make('category_id', 'Kategori')->options(...) ];
    }
}
```

**Yeni bir liste ekranı yazmak 20 satırı geçmemeli.**

## Türkçe arama

```php
$normalized = mb_strtolower(strtr($term,
    ['ı'=>'i','İ'=>'i','ş'=>'s','Ş'=>'s','ğ'=>'g','Ğ'=>'g',
     'ü'=>'u','Ü'=>'u','ö'=>'o','Ö'=>'o','ç'=>'c','Ç'=>'c']), 'UTF-8');
```
PostgreSQL tarafında `unaccent` veya `ILIKE` ile eşleştir.

## Kritik kurallar
- Arama ve sayfalama **sunucu tarafında** yapılır; tüm satırlar çekilmez
- Maliyet kolonu `cost.view` izni yoksa **tanıma hiç eklenmez**
- Dışa aktarma o anki filtreye uyar, tüm tabloyu vermez

## Kabul ölçütü
- 10.000 satırlık tabloda arama 300 ms altında
- Dışa aktarma filtrelenmiş satırları veriyor
- Yetkisiz kullanıcıda maliyet kolonu çıktı HTML'inde **yok**

## İstem
> DataTableComponent adında bir Livewire bileşeni yaz. Column, SelectFilter
> ve DateRangeFilter sınıflarını yaz. Yukarıdaki yetenek tablosundaki her
> maddeyi karşılasın. Arama ve sayfalama sunucu tarafında olsun. Türkçe
> karakter normalizasyonunu uygula. Blade şablonunu tema sınıflarıyla yaz,
> yeni CSS dosyası açma.

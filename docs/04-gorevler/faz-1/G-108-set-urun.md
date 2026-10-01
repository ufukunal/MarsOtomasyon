# G-108 — Set ürün

**Veritabanı: DÖNEM.** Model `PeriodModel`'den türer, migration
`database/migrations/period/` altına yazılır. `company_id` kolonu YOKTUR.


## Amaç
Bileşenlerden oluşan, kendi stoğu olmayan ürün.

## Önkoşul
G-106


## Dokunulacak dosyalar
- `database/migrations/period/`

## Şema / Kod
`product_sets`: `id, set_product_id, component_product_id, quantity decimal(18,3), version`, unique(set, component)

## Satılabilirlik hesabı

```php
public function setAvailability(Product $set): string
{
    $components = $set->setComponents()->with('componentProduct')->get();

    if ($components->isEmpty()) {
        return '0';
    }

    $minimum = null;

    foreach ($components as $component) {
        $stock = $component->componentProduct->availableQuantity(); // decimal string
        $required = $component->quantity;                           // decimal string

        if (bccomp($required, '0', 3) <= 0) {
            return '0';
        }

        // Pozitif değerlerde scale=0 tam karşılanabilir set adedini verir.
        $count = bcdiv($stock, $required, 0);
        $minimum = $minimum === null || bccomp($count, $minimum, 0) < 0
            ? $count
            : $minimum;
    }

    return $minimum ?? '0';
}
```

**Bir bileşen tek seti bile karşılayamıyorsa sonuç 0'dır ve set tüm
kanallarda satışa kapanır.** Bu, kanal senkronizasyonunda (Faz 9)
stok 0 gönderilmesi demektir.

## Kurallar
- Setin kendi stok hareketi **yoktur**
- Satışta bileşenler düşer, set düşmez
- Set ürün başka bir setin bileşeni **olamaz** (döngü kontrolü)
- Bileşen miktarı 0 veya negatif olamaz

## Ekran
Ürün formunda tip `set` seçilince "Bileşenler" sekmesi açılır:
ürün seçici (lookup) + miktar, satır ekle/sil, altta hesaplanan
satılabilir adet gösterilir.


### Göreve özel kararlar
- Setin kendi fiziksel stoğu yoktur.
- Satılabilir miktar bileşen kullanılabilir stoklarının min(component/required) hesabıdır.
- Set bileşen türetimi için integrity testi yaz.


### Uygulama ayrıntıları
- Set ürünün ayrı fiziksel stok bakiyesi yoktur; satılabilir miktar bileşenlerin kullanılabilir stoklarından türetilir.
- Set bileşeni product_id + gerekli miktar ile period içi gerçek FK kullanır.
- Satılabilir set miktarı `min(component_available / required_quantity)` kuralına göre hesaplanır.
- Türetilmiş set uygunluğu için ilgili integrity/test senaryosu bulunur; business stok hareketi set bakiye tablosuna yazılmaz.

## Kabul ölçütü
- 3 bileşenli set, en kısıtlı bileşene göre adet veriyor
- Bir bileşen 0 olunca set 0 oluyor
- Set içine set eklenemiyor
- Faz 2 gelmeden hesap `'0'` döner, hata vermez
- Hesapta PHP float/floor kullanılmıyor
- Set bileşeni stale `version` ile güncellenemiyor


## İstem
> product_sets tablosu için migration, ProductSet modelini, setAvailability
> hesabını BCMath/decimal string ile ve ürün formundaki Bileşenler sekmesini yaz. Set içine set
> eklenmesini engelle. Bileşen miktarı pozitif olmalı.

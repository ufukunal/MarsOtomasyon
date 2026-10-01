# G-108 — Set ürün

**Veritabanı: DÖNEM.** Model `PeriodModel`'den türer, migration
`database/migrations/period/` altına yazılır. `company_id` kolonu YOKTUR.


## Amaç
Bileşenlerden oluşan, kendi stoğu olmayan ürün.

## Önkoşul
G-106

## Şema
`product_sets`: `id, company_id, set_product_id, component_product_id,
quantity decimal(18,3)`, unique(set, component)

## Satılabilirlik hesabı

```php
public function setAvailability(Product $set): float
{
    $components = $set->setComponents()->with('componentProduct')->get();

    if ($components->isEmpty()) {
        return 0;
    }

    return $components->min(function ($c) {
        $stock = $c->componentProduct->availableQuantity();   // Faz 2
        return $c->quantity > 0 ? floor($stock / $c->quantity) : 0;
    });
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

## Kabul ölçütü
- 3 bileşenli set, en kısıtlı bileşene göre adet veriyor
- Bir bileşen 0 olunca set 0 oluyor
- Set içine set eklenemiyor
- Faz 2 gelmeden hesap 0 döner, hata vermez

## İstem
> product_sets tablosu için migration, ProductSet modelini, setAvailability
> hesabını ve ürün formundaki Bileşenler sekmesini yaz. Set içine set
> eklenmesini engelle. Bileşen miktarı pozitif olmalı.

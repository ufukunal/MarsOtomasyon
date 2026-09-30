# Maliyet hesabı

## Yöntem: hareketli ortalama

Tek yöntem budur (K-006). FIFO veya parti bazlı maliyet **yoktur**, çünkü
parti/lot takibi kapsam dışıdır (K-005).

## Formül

Her **giriş** hareketinde:

```
yeni_ortalama = (mevcut_miktar × mevcut_ortalama + giren_miktar × giren_fiyat)
                ÷ (mevcut_miktar + giren_miktar)
```

**Çıkış** hareketinde ortalama değişmez; çıkışın birim maliyeti o anki
ortalamadır.

## Kod

```php
final class UpdateMovingAverage
{
    public function handle(Product $product, float $incomingQty, float $incomingUnitCost): float
    {
        $cost    = ProductCost::firstOrCreate(['product_id' => $product->id]);
        $current = $this->totalQuantity($product);      // tüm lokasyonlar toplamı

        if ($current + $incomingQty <= 0) {
            return $incomingUnitCost;                    // stok yoksa gireni al
        }

        $newAvg = (($current * $cost->moving_average) + ($incomingQty * $incomingUnitCost))
                  / ($current + $incomingQty);

        $cost->update([
            'moving_average'      => $newAvg,
            'last_purchase_price' => $incomingUnitCost,
            'last_purchase_at'    => now(),
        ]);

        return $newAvg;
    }
}
```

## Dikkat edilecek durumlar

**Stok sıfır veya negatifken giriş:** ortalama hesaplanamaz, giren fiyat
doğrudan maliyet olur.

**Negatif stokta çıkış:** o anki ortalama kullanılır. Stok negatifken
ortalama anlamını yitirir; bu yüzden negatif stok ürün bazında izinlidir
ve uyarı verilir.

**Transfer:** maliyeti **değiştirmez**. Çıkış ve giriş aynı birim maliyetle
yazılır.

**İade:** satış iadesinde mal karantinaya girer; maliyeti çıkıştaki
maliyetidir, ortalama yeniden hesaplanmaz.

## Fiyat sapma uyarısı

Alış girişinde birim fiyat mevcut ortalamadan **±%25** saparsa:

- Ekranda uyarı gösterilir: "Bu ürünün ortalama maliyeti 2.513 ₺, girilen
  fiyat 4.100 ₺ (%63 yüksek). Devam edilsin mi?"
- Kullanıcı devam ederse kayıt yapılır, satır işaretlenir
- `activity_log`'a düşer

Eşik `companies.cost_deviation_threshold` (varsayılan 25), ürün grubundan
ezilebilir.

**Amaç hatalı giriş yakalamaktır, iş durdurmak değil.** Bu yüzden engel değil,
uyarıdır (K-007).

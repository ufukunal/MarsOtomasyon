# Birim dönüşümü

**Sessiz stok hatasının en olası kaynağı budur.** Ürün kutuyla alınıp
adetle satılıyorsa ve hareket birimi karışırsa stok altı katı yanlış olur.

## Temel kural

> **Stok hareketi HER ZAMAN ürünün temel biriminde yazılır.**
> Belge satırındaki birim ne olursa olsun, harekete geçmeden önce
> temel birime çevrilir.

`products.unit_id` ürünün **temel birimidir**. `stock_movements.quantity`
her zaman bu birimdedir ve hareket satırında birim kolonu **yoktur** —
olması karışıklık yaratır.

## Belge satırı

Belge satırı kullanıcının seçtiği birimi saklar **ve** temel birim
karşılığını da saklar:

```php
$table->foreignId('unit_id');                        // kullanıcının seçtiği
$table->decimal('quantity', 18, 3);                  // seçilen birimde
$table->decimal('base_quantity', 18, 3);             // TEMEL BİRİMDE
$table->decimal('conversion_factor', 18, 6);         // o anki katsayı — DONDURULUR
```

`base_quantity = quantity × conversion_factor`

**Katsayı belgede dondurulur.** Dönüşüm tanımı sonradan değişirse
(1 KUTU 6 adetten 8 adete çıkarsa) geçmiş belgeler bozulmaz.

## Fiyat hangi birimde

Birim fiyat **satırdaki birime aittir**. 1 kutu 1.200 TL ise
`unit_price = 1200`, `unit_id = KUTU`.

Satır toplamı `quantity × unit_price` ile hesaplanır — temel birimle
değil. Aksi halde tutar altı katına çıkar.

Maliyet ise **temel birimdedir**: `unit_cost = satır maliyeti ÷ base_quantity`.

## Örnek

```
Ürün: KRST-K9-14, temel birim ADET
Dönüşüm: 1 KUTU = 500 ADET

Belge satırı: 3 KUTU × 2.500 TL
  quantity          = 3
  unit_id           = KUTU
  conversion_factor = 500
  base_quantity     = 1.500
  unit_price        = 2.500 TL   (kutu fiyatı)
  line_total        = 7.500 TL

Stok hareketi:
  quantity  = 1.500        (ADET — temel birim)
  unit_cost = 5 TL         (7.500 ÷ 1.500)
```

Hareket 3 olarak yazılsaydı stokta 1.497 adet eksik görünürdü.

## Dönüşüm bulunamazsa

Ürünün temel birimine dönüşüm tanımlı değilse **işlem engellenir**.
Varsayılan katsayı 1 kabul edilmez — sessizce yanlış stok yazmaktansa
hata vermek iyidir.

## Sayım ve transfer

Sayımda kullanıcı kutuyla sayabilir; sistem temel birime çevirip
karşılaştırır. Fark **temel birimde** gösterilir.

Transfer de temel birimde yazılır.

## Bütünlük kontrolü

`integrity:units` — belge satırlarında
`base_quantity = quantity × conversion_factor` eşitliğini doğrular.
Kapsam matrisine eklendi.

## Test

```php
it('kutu ile satista stok adet olarak duser', function () {
    // 1 KUTU = 500 ADET, stok 2000 ADET
    fatura(urun: 'KRST-K9-14', miktar: 3, birim: 'KUTU', fiyat: 2500);

    expect(stok('KRST-K9-14'))->toBe(500.0);        // 2000 - 1500
    expect(hareket()->quantity)->toBe(1500.0);
    expect(hareket()->unit_cost)->toBe(5.0);
});
```

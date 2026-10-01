# G-203 — Hareketli ortalama ve sapma uyarısı

**Veritabanı: DÖNEM.**

## Amaç

`product_costs.moving_average` alanını tek geçerli maliyet olarak güvenli ve deterministik biçimde güncellemek.

## Önkoşul

G-202.

## Dokunulacak dosyalar

- `app/Actions/Stock/UpdateMovingAverage.php`
- `app/Actions/Stock/CheckPurchaseCostDeviation.php`
- `tests/Feature/Stock/MovingAverageTest.php`

## Şema / Kod

Formül:

```
yeni_ortalama =
  (mevcut_miktar × mevcut_ortalama + giren_miktar × giren_fiyat)
  ÷ (mevcut_miktar + giren_miktar)
```

Tüm değerler string/BCMath:

```php
final class UpdateMovingAverage
{
    public function handle(
        int $productId,
        string $incomingQty,
        string $incomingUnitCost
    ): string {
        $cost = ProductCost::query()
            ->where('product_id', $productId)
            ->lockForUpdate()
            ->firstOrCreate(
                ['product_id' => $productId],
                ['moving_average' => '0.0000']
            );

        $currentQty = StockBalance::query()
            ->where('product_id', $productId)
            ->sum('quantity'); // DB decimal sonucu string olarak ele alınır

        if (bccomp((string) $currentQty, '0', 3) <= 0) {
            $newAvg = bcadd($incomingUnitCost, '0', 4);
        } else {
            $currentValue = bcmul(
                (string) $currentQty,
                (string) $cost->moving_average,
                4
            );

            $incomingValue = bcmul($incomingQty, $incomingUnitCost, 4);
            $newValue = bcadd($currentValue, $incomingValue, 4);
            $newQty = bcadd((string) $currentQty, $incomingQty, 3);

            $newAvg = bcdiv($newValue, $newQty, 4);
        }

        $cost->moving_average = $newAvg;
        $cost->save();

        return $newAvg;
    }
}
```

Not: `RecordStockMovement` içindeki kilit sırası bu action ile uyumlu tutulur. Aynı ürün için farklı kilit sırası oluşturma.

## Kenar durumlar

| Durum | Davranış |
|---|---|
| Stok 0 veya negatifken giriş | Giren fiyat doğrudan moving average |
| Çıkış | Ortalama değişmez |
| Transfer | Ortalama değişmez |
| Satış iadesi | K-015/Faz 6 karantina akışına göre; bu görev yeni kural üretmez |
| Opening | Kaynak kapanış moving average kullanılır |

## Alış fiyatı sapma uyarısı

K-007: varsayılan eşik şirket kartındaki `companies.cost_deviation_threshold` (default 25).

Sapma hesabı da BCMath ile yapılır. `moving_average = 0` ise yüzde sapma uyarısı hesaplanmaz.

Uyarı:

- işlemi bloklamaz,
- kullanıcı devam ederse period activity_log'a kaydedilir,
- `cost.view` olmayan kullanıcıya mevcut maliyet tutarı sızdırılmaz.

Kategori bazlı ek bir eşik alanı güncel veri modelinde tanımlı değildir; bu görev böyle bir alan **uydurmaz**.

## Kabul ölçütü

- 10 × 100 ardından 10 × 200 → 150.0000.
- Çıkış sonrası 150.0000 kalıyor.
- Stok 0/negatifken giriş maliyeti doğrudan yeni ortalama oluyor.
- %30 sapmada uyarı, %20'de uyarı yok (eşik 25).
- Hesap kodunda float kullanılmıyor.
- `cost.view` olmayan kullanıcıya maliyet rakamı dönmüyor.
- Gerçek PostgreSQL concurrency testi geçiyor.

## İstem

> UpdateMovingAverage ve alış maliyet sapma kontrolünü bu görevdeki BCMath/string sözleşmesiyle uygula. Kategori eşiği gibi kaynakta olmayan alan ekleme. Şirket eşiğini Master companies.cost_deviation_threshold üzerinden al; cost.view veri sızıntısını engelle.

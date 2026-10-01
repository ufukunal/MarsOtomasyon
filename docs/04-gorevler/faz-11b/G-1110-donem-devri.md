# G-1110 — Dönem devri

## Amaç
Yıl sonunda bakiyelerin bir sonraki döneme taşınması.
**Hareketli ortalama maliyet yıl sınırında kopmamalıdır.**

## Önkoşul
Faz 2 (stok), Faz 3 (cari hareket), Faz 5 (kasa/banka/çek)

## Ekran
`docs/03-ekranlar/donem-devri.md` belgesini uygula.

## Action

```php
final class CarryPeriod
{
    public function handle(Period $source, int $targetYear): CarryResult
    {
        // 1. hedef dönem oluştur (CreatePeriod)
        // 2. kaynak dönemden oku:
        //    - stock_balances (quantity > 0)
        //    - product_costs (moving_average)
        //    - contact_transactions toplamı (cari bakiye)
        //    - cash/bank bakiyeleri
        //    - vadesi gelmemiş çek/senet
        // 3. hedef döneme yaz:
        //    - her ürün/lokasyon için RecordStockMovement(
        //          direction: in, reason: opening,
        //          unitCost: KAPANIŞ HAREKETLİ ORTALAMASI )
        //    - product_costs kopyala
        //    - cari açılış fişi
        //    - kasa/banka açılış
        //    - çek/senet taşı
        // 4. kaynak dönem status = closed
        // 5. periods: carried_from_period_id, carried_at
    }
}
```

## Kritik kurallar

**Maliyet sürekliliği.** Açılış hareketinin birim maliyeti, kaynak
dönemin kapanış hareketli ortalamasıdır. Sıfır veya son alış fiyatı
**değildir**.

**Kartlar taşınmaz.** Cari, ürün, fiyat listesi master'dadır.

**Taşınmayanlar:** açık sipariş, açık teklif, taslak belge, yolda
transfer, karantinada bekleyen kalem. Kullanıcı kontrol listesinde uyarılır.

**Devir bir kez yapılır.** `carried_at` doluysa tekrar çalıştırılamaz.

**Geri alma:** hedef veritabanı silinir, `periods` satırı kaldırılır,
kaynak `active` yapılır. Hedefe kayıt girildiyse uyarı verilir.

## Önizleme
Hiçbir şey yazmadan taşınacakları listeler: ürün sayısı, toplam miktar,
toplam stok değeri, cari sayısı, toplam bakiye.

## Kabul ölçütü
- Devir sonrası hedef dönemde stok miktarları kaynakla aynı
- Açılış birim maliyeti = kaynak kapanış ortalaması
- Hedef dönemde `product_costs` dolu
- Cari bakiyeleri toplamı korunuyor
- İkinci devir denemesi reddediliyor
- Geri alma kaynak dönemi `active` yapıyor
- Açık siparişler taşınmıyor ve kontrol listesinde uyarılıyor

## İstem
> CarryPeriod action'ını, devir öncesi kontrol listesini, önizleme
> fonksiyonunu ve Dönem Devri ekranını yaz. Açılış hareketinin birim
> maliyeti kaynak dönemin kapanış hareketli ortalaması olsun.
> Kartları TAŞIMA — master'dalar. Devir bir kez yapılabilsin.

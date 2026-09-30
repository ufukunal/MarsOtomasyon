# G-208 — Stok sayımı

## Amaç
Fiziksel sayım ve fark düzeltmesi. **Fark elle onaylanır**, otomatik
hareket oluşmaz.

## Önkoşul
G-202

## Şema

```php
// stock_counts:
//   id, company_id, number, location_id, count_date, status(draft|counting|review|posted|cancelled),
//   note, created_by, posted_by, posted_at
// stock_count_lines:
//   id, stock_count_id, product_id,
//   system_quantity decimal(18,3),    // sayım başlarken DONDURULUR
//   counted_quantity decimal(18,3)->nullable,
//   difference decimal(18,3),          // counted - system
//   is_approved bool default false,
//   note
```

## Akış

```
1. Sayım açılır (lokasyon + ürün aralığı seçilir)
   → status = draft
2. "Sayımı Başlat"
   → o andaki stok system_quantity olarak DONDURULUR
   → status = counting
   → uyarı: "Sayım sırasında bu lokasyonda hareket yapılmaması önerilir"
3. Sayılan miktarlar girilir (barkod okuyucuyla da girilebilir)
4. "Farkları Göster"
   → status = review
   → yalnızca farkı olan satırlar listelenir
5. Kullanıcı satır satır onaylar (is_approved)
6. "Sayımı Kesinleştir"
   → yalnızca ONAYLI satırlar için düzeltme hareketi yazılır
   → reason = count, direction fark işaretine göre
   → status = posted, numara verilir
```

## Kurallar
- `system_quantity` sayım **başlarken** dondurulur; sonraki hareketler
  bu değeri değiştirmez
- Onaylanmayan satır için hareket **oluşmaz**
- Kesinleşen sayım değiştirilemez
- Kesinleştirme tek transaction içinde
- Dönem kapalıysa kesinleştirilemez

## Ekran
Liste: numara, lokasyon, tarih, durum, satır sayısı, farklı satır sayısı.
Detay: ürün, sistem miktarı, sayılan, fark (renkli), onay kutusu, not.
Üstte: "Yalnızca farklıları göster" filtresi.

## Kabul ölçütü
- Sayım başlatınca sistem miktarı donuyor
- Sayım sırasında yapılan satış `system_quantity`'yi değiştirmiyor
- Onaylanmayan satır için hareket oluşmuyor
- Kesinleşen sayım değiştirilemiyor
- Fark kadar hareket yazılıyor, bakiye sayılan miktara eşitleniyor

## İstem
> stock_counts ve stock_count_lines tabloları için migration, modeller,
> liste ve detay ekranlarını, sayım başlatma ve kesinleştirme action'larını
> yaz. system_quantity sayım başlarken dondurulsun. Yalnızca onaylı
> satırlar için RecordStockMovement çağrılsın.

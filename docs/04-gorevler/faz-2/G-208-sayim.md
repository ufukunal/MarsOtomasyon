# G-208 — Stok sayımı

**Veritabanı: DÖNEM.** Model `PeriodModel`'den türer, `company_id` kolonu
yoktur, migration `database/migrations/period/` altına yazılır.


## Amaç
Fiziksel sayım ve fark düzeltmesi. **Fark elle onaylanır**, otomatik
hareket oluşmaz.

## Önkoşul
G-202


## Dokunulacak dosyalar
- stock_counts/stock_count_lines period migration'ları
- StockCount/Line period modelleri
- sayım list/detail Livewire + blade
- StartStockCount/PostStockCount Action'ları
- `tests/Feature/Stock/StockCountTest.php`

## Şema / Kod
```php
// stock_counts:
//   id, number, location_id, count_date, status(draft|counting|review|posted|cancelled),
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
   → yalnızca ONAYLI satırlar için **sayım başlangıcında dondurulmuş fark** kadar düzeltme hareketi yazılır
   → reason = count, direction snapshot fark işaretine göre
   → sayım başladıktan sonra oluşmuş meşru stok hareketleri ayrıca korunur
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


### Göreve özel kararlar
- Sayım sistem miktarını snapshot eder; fark kullanıcı onayı olmadan uygulanmaz.
- Onay sonrası yalnız `counted_quantity - frozen system_quantity` snapshot farkı kadar `RecordStockMovement` yazılır. Sayım başladıktan sonra oluşan hareketler geri alınmaz veya ezilmez.


### Uygulama ayrıntıları
- Sayım başlangıcında sistem miktarı snapshot alınır; kullanıcı sayılan miktarı girer.
- Fark kullanıcı onayı olmadan stok hareketi oluşturmaz.
- Onay sonrası yalnız fark kadar `RecordStockMovement` çağrılır; tam miktar yeniden yazılmaz.
- Sayım geçmişi ve fark gerekçesi audit için korunur.

## Kabul ölçütü
- Sayım başlatınca sistem miktarı donuyor
- Sayım sırasında yapılan satış `system_quantity`'yi değiştirmiyor
- Onaylanmayan satır için hareket oluşmuyor
- Kesinleşen sayım değiştirilemiyor
- Fark kadar hareket yazılıyor; sayım sonrası hareket yoksa bakiye sayılan miktara eşitleniyor
- Sayım başladıktan sonra +/− hareket olduysa final bakiye = posting öncesi güncel bakiye + frozen snapshot farkı; sonraki hareketler kaybolmuyor


## İstem
> stock_counts ve stock_count_lines tabloları için migration, modeller,
> liste ve detay ekranlarını, sayım başlatma ve kesinleştirme action'larını
> yaz. system_quantity sayım başlarken dondurulsun. Yalnızca onaylı
> satırlar için RecordStockMovement çağrılsın.

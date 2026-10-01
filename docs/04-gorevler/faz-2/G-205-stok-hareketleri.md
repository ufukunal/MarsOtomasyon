# G-205 — Stok Hareketleri ekranı

**Veritabanı: DÖNEM.** Model `PeriodModel`'den türer, `company_id` kolonu
yoktur, migration `database/migrations/period/` altına yazılır.


## Amaç
Bir ürünün ya da lokasyonun tüm hareket geçmişi. Sorun araştırmanın
başladığı ekran: "bu ürünün stoğu neden 3 kaldı?"

## Önkoşul
G-202

## Kolonlar
Tarih · Ürün · Lokasyon · Yön (rozet: Giriş/Çıkış) · Sebep · Miktar ·
Birim Maliyet (`cost.view`) · Tutar (`cost.view`) · **Kalan Bakiye**
(`balance_after`) · Belge No (tıklanınca belgeye gider) · Kullanıcı

## Filtreler
Tarih aralığı, ürün, lokasyon, yön, sebep, belge türü, kullanıcı

## Sebep etiketleri
purchase → Alış · sale → Satış · transfer → Transfer · count → Sayım ·
production → Üretim · return → İade · scrap → Hurda · opening → Açılış

## Kritik detay
`balance_after` kolonu **hareket anında saklanan** değerdir, ekranda
yeniden hesaplanmaz. Tarih sırasına göre bakıldığında bakiyenin nasıl
oluştuğu adım adım görünür.

## Ürün detayı bağlantısı
Ürün detayındaki "Stok Hareketleri" sekmesi bu ekranın ürüne filtrelenmiş
halidir; ayrı ekran yazılmaz.

## Kabul ölçütü
- Tarih sırasına göre bakiye sürekliliği doğru
- Belge numarasına tıklayınca belge açılıyor
- `cost.view` izni olmayanda maliyet kolonları yok
- 100.000 harekette sayfalama akıcı

## İstem
> StockMovements Livewire ekranını DataTableComponent üzerine yaz.
> balance_after saklanan değerden okunsun, yeniden hesaplanmasın.
> Sebep etiketleri Türkçe gösterilsin. Ürün detayında aynı bileşen
> filtrelenmiş olarak kullanılsın.

# Veritabanı mimarisi — ince master + şirket/dönem

**Bu belge diğer her şeyin üstündedir.**

## Yapı

```
MarsProject_Master     şirketler, dönemler, kullanıcılar, roller, izinler,
                       genel ayarlar, döviz kurları.  BAŞKA HİÇBİR ŞEY.

ABCHolding_2026        HER ŞEY: cari ve ürün kartları, varyant, set,
ABCHolding_2027        konfigüratör, fiyat listeleri, lokasyonlar, görseller,
XYZltd_2026            belgeler, stok hareketleri, bakiye, maliyet,
                       cari hareketler, kasa/banka, çek/senet,
                       numara serileri, ay kilitleri, sayımlar, ekler.
```

Logo/Mikro modeli. Kullanıcı giriş yapar, **şirket ve dönem seçer**,
uygulama o veritabanına bağlanır ve **o veritabanında her şeyi bulur**.

## Neden kartlar da dönemde

**Dönemin anlamı temiz geçiştir.** 2026'da bir ürünün fiyatını veya adını
değiştirmek 2025'in faturalarını etkilememelidir. Kart o yılın içinde donar.

İki büyük kazanç:

**Arşivlenen yıl kendi başına açılır.** Başka veritabanına ihtiyaç yok.

**Veritabanları arası yabancı anahtar sorunu yok.** Belge aynı
veritabanındaki cariye işaret eder, gerçek `foreign key` kurulur.
Kart bilgisi belgeye yine kopyalanır (`contact_title`, `product_name`)
ama bu artık zorunluluk değil, belge dökümü kolaylığıdır.

**Maliyeti:** bir ürünün adını düzeltince yalnızca aktif dönemde düzelir.
Çoğu durumda doğru davranış budur.

## Master'da ne var

| Tablo | Not |
|---|---|
| `companies` | şirket kartı, `db_prefix` |
| `periods` | hangi şirketin hangi yılı, hangi veritabanı |
| `users`, `roles`, `permissions`, `company_user` | yetki |
| `exchange_rates` | döviz kurları |
| `app_settings` | sürüm, genel parametreler |
| `activity_log` (master işlemleri) | giriş, yetki değişikliği, dönem açma |

**Master'da kart yoktur.** `company_copy_permissions` de kalkmıştır — şirketler arası
kopyalama artık dönem veritabanları arasında yapılır (izin `companies`
tablosunda tutulur).

## Dönem veritabanında ne var

Kartlar: `contacts` + adres/yetkili/banka/kategori, `products` +
kategori/marka/birim/barkod/varyant/set/konfigürasyon, `price_lists`,
`locations`, `attachments`.

Hareketler: `documents`, `document_lines`, `stock_movements`,
`stock_balances`, `product_costs`, `contact_transactions`,
`cash_movements`, `bank_movements`, `securities`, `number_series`,
`posting_periods`, `stock_counts`, `quarantine_entries`,
`stock_reservations`, `integrity_reports`, `idempotency_keys`,
`activity_log` (dönem işlemleri).

**Dönem tablolarında `company_id` kolonu YOKTUR.** Veritabanı zaten o
şirkete ve yıla aittir. Global scope da yoktur — izolasyon fizikseldir.

## Bağlantı

```php
'master' => ['database' => env('DB_MASTER_DATABASE', 'MarsProject_Master')],
'period' => ['database' => null],          // çalışma anında doldurulur

PeriodContext::use($companyId, $year);
  → config(['database.connections.period.database' => $period->database_name])
  → DB::purge('period'); DB::reconnect('period');
```

Model temel sınıfları:

```php
abstract class MasterModel extends Model { protected $connection = 'master'; }
abstract class PeriodModel extends Model { protected $connection = 'period'; }
```

Master modelleri de global scope **kullanmaz** — `companies`, `periods`,
`users` zaten şirket üstüdür.

## Migration klasörleri

```
database/migrations/master/
database/migrations/period/
```

Dağıtımda `migrate --database=master` ve ardından **`migrate:periods`**
(tüm dönem veritabanları) çalıştırılır.

## Dönem devri

Yıl sonunda yeni veritabanı oluşturulur ve:

```
1. KARTLAR KOPYALANIR — cari, ürün, varyant, set, konfigürasyon,
   fiyat listeleri, lokasyonlar, kart ekleri
2. Stok açılışı yazılır — her ürün/lokasyon için giriş hareketi,
   birim maliyet = KAPANIŞ HAREKETLİ ORTALAMASI
3. product_costs taşınır
4. Cari bakiyeleri açılış fişi olur
5. Kasa, banka bakiyeleri ve vadesi gelmemiş çek/senet taşınır
6. Kaynak dönem kapatılır (salt okunur)
```

**Taşınmayanlar:** belgeler, hareketler, açık sipariş/teklif, taslaklar,
yolda transfer, karantinada bekleyen kalem.

## Şirketler arası kopyalama

İzin `companies` tablosunda tanımlı (hangi şirket hangisinden kart
alabilir). Kopyalama kaynak şirketin **aynı yıldaki** dönem
veritabanından hedefin dönem veritabanına yapılır; kaynak referansı
saklanır, canlı bağ kurulmaz.

## Yedekleme

Her veritabanı ayrı yedeklenir. Master küçüktür ama **her yedekte olmalı**
— onsuz hangi veritabanının hangi şirkete ait olduğu bilinmez.

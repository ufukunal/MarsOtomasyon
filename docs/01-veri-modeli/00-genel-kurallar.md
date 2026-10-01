# Veri modeli — genel kurallar

**Önce oku:** `docs/00-genel/07-veritabani-mimarisi.md`

Sistem **master + şirket/dönem** veritabanı modeli kullanır.
Her tablo dosyasının başında **hangi veritabanında durduğu** yazılıdır.

## Master tabloları

Yalnızca: `companies`, `periods`, `users`, `roles`, `permissions`,
`company_user`, `exchange_rates`, `app_settings`, `activity_log`.
Bunlar şirket üstüdür, `company_id` taşımazlar (ilişki tabloları hariç).

**Master'da kart yoktur.**

## Dönem tabloları — diğer HER ŞEY

`company_id` kolonu **yoktur.** Veritabanının kendisi o şirkete ve yıla
aittir; izolasyon fizikseldir, global scope yoktur.

Kartlar da dönemdedir, bu yüzden belgeler **gerçek yabancı anahtar**
kullanır (`documents.contact_id` → `contacts.id`). Kart bilgisi belgeye
yine kopyalanır (`contact_title`, `product_name`) ama bu zorunluluk
değil, belge dökümü kolaylığıdır.

## Silme politikası

İş kayıtları silinmez. `deleted_at` yalnızca master'daki kart
tablolarında bulunur. Belgeler iptal edilir, ters kayıt yazılır.

## Sayısal tipler

Tutar `decimal(18,4)`, miktar `decimal(18,3)`, oran `decimal(7,4)`,
kur `decimal(18,6)`. **Float yasak.**

## CHECK kısıtı kuralı

Her tablonun **ihlal edilemez** kuralları veritabanı seviyesinde CHECK
kısıtı olarak yazılır. "Uygulama zaten kontrol ediyor" gerekçesi kabul
edilmez — içe aktarma, kuyruk işi ve elle SQL uygulamayı atlar.

Örnek: `quantity > 0`, `direction IN ('in','out')`, `vat_rate BETWEEN 0 AND 100`,
`abs(total_cost - quantity * unit_cost) < 0.01`.

Ayrıntı: `docs/02-is-kurallari/16-veri-butunlugu.md`

## İndeks kuralı

Her yabancı anahtar indekslenir. Master'da `company_id + code`,
dönemde `product_id + location_id + date` bileşik indeks alır.

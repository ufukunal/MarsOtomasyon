# G-113 — Ürün görsel setleri

**Veritabanı: DÖNEM.** Model `PeriodModel`'den türer, migration
`database/migrations/period/` altına yazılır. `company_id` kolonu YOKTUR.


## Önkoşul
G-106 (ürün kartı), G-009 (attachments)

## Dokunulacak dosyalar
- `database/migrations/period/`


## Şema / Kod

Bu bölümdeki mevcut şema örnekleri aşağıdaki kanonik mimari kurallarla birlikte uygulanır. Çelişkide kanonik kurallar üstündür.

## Amaç
Her platform için ayrı görsel: Trendyol ayrı, Hepsiburada ayrı, N11 ayrı,
WooCommerce siteleri bazıları ortak bazıları ayrı.

## Model
Görseller `attachments` tablosunda tutulur (G-009). Platform ayrımı
`collection` kolonuyla yapılır.

Set adları: `Ortak`, `Trendyol`, `Hepsiburada`, `N11`, `Site-A`, `Site-B`

## Ekran — Ürün > Görseller sekmesi
Set seçici (sekme ya da açılır liste), her sette görsel ızgarası,
sürükleyerek sıralama, ilk görsel ana görsel, yükle/sil.

## Kurallar
- **Sıkıştırma yok** (K-012) — kullanıcı dosyayı kendisi optimize eder
- Bir kanalın seti yoksa `Ortak` sete düşer
- Sıralama `sort_order` ile, ilk sıradaki ana görseldir
- İzin verilen türler: jpg, jpeg, png, webp
- Faz 9'da kanal senkronizasyonu bu setleri kullanır


### Göreve özel kararlar
- Ürün görselleri period attachments'ta platform `collection` setleriyle tutulur.
- Dosya MIME/UUID/SVG güvenlik kuralları zorunlu.
- Görsel setleri dönem devrinde kartla taşınır.


### Uygulama ayrıntıları
- Ürün görselleri period `attachments` altyapısını kullanır; kanal/platform seti `collection` ile ayrılır.
- Fiziksel dosya güvenliği attachment kuralıyla aynıdır: içerik MIME, UUID ad, SVG yasağı.
- Görsel sırası açık `sort_order` ile tutulur; UI sıra değiştirme yalnız metadata günceller.
- Dönem devrinde ürünle ilişkili görsel setleri ve fiziksel dosyalar birlikte taşınır.

## Kabul ölçütü
- Aynı ürüne iki farklı sete görsel yükleniyor
- Set boşsa Ortak sete düşülüyor
- Sıralama kalıcı
- Farklı şirketin görseli görünmüyor


## İstem
> Ürün detayına Görseller sekmesi ekle. Görseller attachments tablosunda
> collection kolonuyla set bazlı tutulsun. Set seçici, ızgara görünümü ve
> sıralama olsun. Sıkıştırma veya yeniden boyutlandırma KOD YAZMA.

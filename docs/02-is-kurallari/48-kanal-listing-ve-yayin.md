# Kanal listing ve yayın

## Kanallar

K-163:

- Trendyol
- Hepsiburada
- N11
- WooCommerce

Tümü ortak adapter sözleşmesine uyar; platform özel API ayrıntıları adapter içinde kalır.

## Kanal hesabı

K-164/K-165:

- Master DB,
- şirket bazında,
- aynı platformdan çoklu mağaza destekli.

Period kayıtları Master account id'yi scalar taşır; cross-DB FK yoktur.

## Listing mapping

K-166/K-167:

- her internal product/varyant ayrı listing,
- external product/listing id + sku mapping'de,
- variant group kanala gönderilmez.

## Yayınlama

K-168:

- yeni listing oluşturma,
- mevcut external listing'e bağlanma

ikisi de desteklenir.

## İçerik kaynağı

K-169/K-200:

Varsayılan:

- title = products.name
- description = products.description

Listing override varsa override kullanılır.

## Görseller

K-170:

1. listing/channel için image_collection,
2. yoksa platform seti,
3. yoksa `Ortak`

fallback uygulanır.

Görseller mevcut attachments altyapısından gelir.

## Kategori/özellik

K-199:

İlk sürümde kanal kategori/özellik metadata'sı listing üzerinde manuel tutulur.

Otomatik kategori matching yoktur.

## Pasifleştirme

K-198:

Mapping silinmez.

- is_active=false,
- desteklenen kanalda listing deactivate veya stock=0,
- history korunur.

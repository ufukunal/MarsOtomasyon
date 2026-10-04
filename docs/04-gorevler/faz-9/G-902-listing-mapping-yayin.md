# G-902 — Listing mapping ve yayın

## Amaç

Internal product ile external listing arasında kalıcı mapping kurmak, mevcut listing'e bağlanmak ve yeni listing yayınlamak.

## Önkoşul

G-901, ürün kartları.

## Dokunulacak dosyalar

- channel_product_listings migration/model
- listing location migration/model
- listing detail Livewire
- link/publish Actions
- listing tests

## Şema / Kod

Mapping:

- channel account
- product
- external product/listing id
- external sku
- override alanları
- active state

## Kurallar

- Her internal varyant ayrı listing.
- Variant group outbound yok.
- Bir account+product tek mapping.
- Existing listing'e bağlama ve yeni publish birlikte.
- Pasifleştirme mapping silmez.
- Category metadata manuel.

## Kabul ölçütü

- Existing external listing bağlanabiliyor.
- Yeni listing adapter'a publish ediliyor.
- Aynı product/account duplicate reddediliyor.
- Varyant grup birleştirme yok.
- Pasif listing history korunuyor.

## İstem

> K-166…K-169/K-198/K-199 listing mapping ve publish akışını uygula.

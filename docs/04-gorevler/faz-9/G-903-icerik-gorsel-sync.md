# G-903 — Kanal içerik ve görsel senkronizasyonu

## Amaç

Internal product içeriğini kanal override ve görsel fallback kurallarıyla outbound senkronlamak.

## Önkoşul

G-902, G-113 görsel setleri.

## Dokunulacak dosyalar

- ChannelContentResolver
- ChannelImageResolver
- content sync job
- content/image tests

## Şema / Kod

Title/description:

- override varsa override,
- yoksa internal product.

Görsel:

1. listing image_collection,
2. platform collection,
3. Ortak.

## Kurallar

- Dış platform değişikliği internal product'u değiştirmez.
- SVG yok; existing attachment security geçerli.
- Otomatik kategori matching yok.
- Hassas payload history'ye yazılmaz.

## Kabul ölçütü

- Override/fallback doğru.
- Kanal seti boşken Ortak görseller gönderiliyor.
- External inbound content internal karta yazılmıyor.
- Görsel sırası korunuyor.
- Category metadata adapter payload'ına doğru taşınıyor.

## İstem

> K-169/K-170/K-192/K-199/K-200 kanal içerik ve görsel resolver'larını uygula.

# G-905 — Kanal fiyat senkronizasyonu

## Amaç

TRY kanal fiyatını listing override veya mevcut fiyat çözümleme zincirinden üretip outbound göndermek.

## Önkoşul

G-902, fiyat listesi altyapısı.

## Dokunulacak dosyalar

- ChannelPriceResolver
- ChannelPriceSync job
- price preview
- price sync tests

## Şema / Kod

```
effective_price =
listing.price_override
?? existing_internal_price_resolution
```

Currency = TRY.

## Kurallar

- External product fiyatı internal product'u mutate etmez.
- Listing override explicit gerçek kayıttır.
- Fiyat change sync event/history üretir.
- Retry ortak kanal retry politikasına uyar.

## Kabul ölçütü

- Override varsa kullanılıyor.
- Override yoksa fallback doğru.
- TRY dışı channel price üretilmiyor.
- External inbound fiyat internal karta yazılmıyor.
- Sync history oluşuyor.

## İstem

> K-171/K-172/K-192 kanal fiyat resolver ve outbound sync işini uygula.

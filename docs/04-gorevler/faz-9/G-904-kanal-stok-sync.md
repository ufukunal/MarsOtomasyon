# G-904 — Kanal stok senkronizasyonu

## Amaç

stock|production|manual modlarında listing bazında gönderilecek quantity'yi hesaplamak ve anlık + 15 dk yedek sync uygulamak.

## Önkoşul

G-902, stok/reservation/quarantine/fason kuralları, Faz 8 production mode.

## Dokunulacak dosyalar

- ChannelStockResolver
- ChannelStockSync job
- 15-minute scanner
- stock preview UI
- stock sync tests

## Şema / Kod

stock mode:

- yalnız selected channel_listing_locations,
- available = quantity-reserved-consignment_reserved-quarantine,
- subcontractor location hariç,
- withhold sonra max cap.

production:

- listing fixed_quantity + lead_time.

manual:

- listing manual_quantity.

Set:

- selected location scope component available min hesabı.

## Kurallar

- Product mode listing override yoksa default.
- Stok movement sonrası gecikmesiz queue.
- Aynı listing pending stock job coalesce.
- Job çalışırken son gerçek quantity tekrar okunur.
- 15 dk scan fallback.

## Kabul ölçütü

- K-174 gereği yalnız listing'e atanmış seçili depolar toplanıyor.
- Subcontractor stock gönderilmiyor.
- Withhold+max doğru.
- production/manual fiziksel stock'tan bağımsız.
- set limiting component doğru.
- Duplicate event fazla API çağrısını coalesce ediyor.

## İstem

> K-173…K-178/K-193 kanal stok resolver ve sync zincirini listing-location kapsamıyla uygula.

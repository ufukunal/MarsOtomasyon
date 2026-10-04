# G-909 — Kanal dönem devri, external registry ve integrity

## Amaç

Listing mapping'i yeni döneme taşımak, external order duplicate engelini period sınırında korumak ve kanal integrity kontrollerini tamamlamak.

## Önkoşul

G-902, G-906, G-908, dönem devri altyapısı.

## Dokunulacak dosyalar

- carry channel mappings
- Master external event registry routing
- IntegrityChannels
- IntegrityChannelOrders
- rollover/integrity tests

## Şema / Kod

Taşınır:

- channel_account_period_settings
- channel_product_listings
- channel_listing_locations
- external listing ids
- listing override/settings

Taşınmaz:

- geçmiş/tamamlanmış channel_order_snapshots
- sync events/errors

K-256 istisnası: target period'a taşınan açık kanal sales_order için gerekli channel_order_snapshot yeni target order'a bağlanarak aktif provenance olarak taşınır.

Master account/event registry yıl bağımsız.

## Kurallar

- Product IDs period rollover'da korunur.
- Event external timestamp hedef açık period routing'inde kullanılır.
- Duplicate registry aynı event'i ikinci period'da oluşturmaz.
- Auto repair yok.

## Kabul ölçütü

- Channel account period marketplace-customer mapping yeni period'da korunuyor.
- Listing mapping yeni period'da korunuyor.
- Old sync/order history taşınmıyor; K-256 carried open channel order için minimal active snapshot korunuyor.
- Aynı external order yıl sınırında duplicate değil.
- integrity:channels invalid location/mapping farkını buluyor.
- integrity:channel-orders registry/order provenance farkını buluyor.

## İstem

> K-196/K-197 kanal mapping rollover ve dönemler arası event idempotency bütünlüğünü uygula.

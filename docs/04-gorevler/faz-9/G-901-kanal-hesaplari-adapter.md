# G-901 — Kanal hesapları ve adapter sözleşmesi

## Amaç

Master sales_channel_accounts yapısını ve Trendyol/Hepsiburada/N11/WooCommerce için ortak adapter sınırını kurmak.

## Önkoşul

K-163…K-165, Master DB bağlantısı.

## Dokunulacak dosyalar

- Master migration/model
- channel account Livewire
- credential encryption
- ChannelAdapter contract
- dört platform adapter iskeleti
- account tests

## Şema / Kod

Kanonik kaynak:

- `docs/01-veri-modeli/42-ecommerce-channels.md`

Adapter en az:

- testConnection
- publishListing
- updateContent
- updatePrice
- updateStock
- fetchOrders
- fetchCancellations
- fetchReturns
- pushShipmentStatus

sözleşmesini taşır.

## Kurallar

- Credential Master DB'de encrypted.
- Aynı platformda çoklu hesap.
- Period iş kaydı account id'yi scalar kullanır.
- Platform-specific API alanları adapter dışına sızmaz.

## Kabul ölçütü

- Dört platform account tipi oluşturulabiliyor.
- Credential plaintext okunmuyor.
- Aynı platformda iki hesap mümkün.
- Adapter resolver doğru platform implementasyonunu seçiyor.
- Connection test audit ediliyor.

## İstem

> K-163…K-165 kanal hesapları ve ortak adapter sınırını kur; platform API ayrıntılarını domain'e sızdırma.

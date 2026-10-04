# Ekran — Kanal Siparişleri

## Amaç

External marketplace siparişlerini mevcut sales_order akışıyla birlikte izlemek.

## Liste

- Kanal
- External Order No
- Internal Sales Order No
- Sipariş Tarihi
- Buyer / Recipient
- Tutar
- Fulfillment Durumu
- Cargo/Package
- Sync Durumu

## Detay

### Internal belge

Normal sales_order detayına bağlantı.

### External snapshot

- buyer name
- recipient
- phone/email
- address
- city/district/postcode
- cargo company/code
- shipment/package ids
- campaign metadata

## Kurallar

- Imported order otomatik confirmed sales_order'dır.
- External fiyat/indirim frozen'dır.
- Sipariş importunda tahsilat yoktur.
- Cancel yalnız fulfill edilmemiş kalan miktarı iptal eder.
- Return event draft sales_return oluşturur.

## Eylemler

- Sales Order'ı Aç
- Sevkiyat Oluştur
- External Durumu Yenile
- Return Taslağını Aç
- Sync History

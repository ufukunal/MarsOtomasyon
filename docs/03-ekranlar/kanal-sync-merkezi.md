# Ekran — Kanal Sync Merkezi

## Amaç

Outbound/inbound entegrasyon işlemlerini, hataları ve manuel retry akışını izlemek.

## Liste

- Tarih/Saat
- Kanal hesabı
- Direction
- Entity
- Action
- External ID
- Status
- Attempts
- Correlation ID
- Error Summary

## Filtreler

- kanal
- direction
- entity
- action
- status
- tarih

## Hata detayı

- güvenli request metadata
- payload hash
- attempt geçmişi
- error summary

Hassas tam payload gösterilmez/saklanmaz.

## Eylemler

- Retry
- Resolved İşaretle
- Manuel Polling Başlat
- Listing/Sipariş Kaynağını Aç

Retry yeni sync event/audit oluşturur.

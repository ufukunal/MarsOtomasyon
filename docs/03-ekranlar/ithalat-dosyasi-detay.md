# Ekran — İthalat Dosyası Detayı

## Sekmeler

- Genel
- Kaynak Alış Faturaları
- Ürünler
- Masraflar
- Dağıtım
- Maliyet Özeti
- Timeline / Audit

## Genel

- Dosya No
- document_date
- durum
- not

## Kaynak Alış Faturaları

Posted purchase_invoice satırları seçilir.

Kurallar:

- aynı invoice line başka import file'a bağlıysa seçilemez,
- satır tam bağlanır; miktar bölünmez,
- aynı dosyada farklı supplier olabilir,
- kaynak satırın frozen currency/exchange_rate/net purchase cost değerleri gösterilir.

## Masraflar

Her expense:

- tür
- kaynak tipi: purchase_invoice | manual
- kaynak belge — varsa
- currency
- frozen exchange_rate
- amount
- base currency amount
- allocation method
- inventory cost'a dahil mi
- açıklama

`other` açıklama zorunlu.

İndirilebilir ithalat KDV'si inventory cost'a dahil işaretlenmez.

## Finalize

Önizleme:

- purchase value
- allocated expenses
- final import cost
- current moving average
- adjustment amount
- projected moving average

Finalize idempotent ve immutable'dır.

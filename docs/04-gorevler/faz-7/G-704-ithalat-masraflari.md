# G-704 — İthalat masrafları

## Amaç

K-116…K-119 kapsamında purchase_invoice veya manual import expense kayıtlarını toplamak.

## Önkoşul

G-701, G-702.

## Dokunulacak dosyalar

- ImportExpense form/list
- purchase invoice expense selector
- manual expense Action
- expense validation tests

## Şema / Kod

Expense type:

- freight
- customs_duty
- insurance
- storage
- customs_brokerage
- port_terminal
- other

Source:

- purchase_invoice
- manual

## Kurallar

- Purchase invoice source kendi frozen currency/exchange_rate kullanır.
- Manual source currency + frozen exchange_rate snapshot taşır.
- amount_base BCMath ile hesaplanır.
- other description zorunlu.
- indirilebilir import VAT inventory cost dışıdır.
- Import file cari hareket üretmez.

## Kabul ölçütü

- Farklı currency expense'ler base currency'e frozen kurla çevriliyor.
- Manual expense cari hareket üretmiyor.
- Purchase invoice expense ikinci cari hareket üretmiyor.
- other açıklamasız reddediliyor.
- inventory-cost flag doğru.

## İstem

> K-116…K-119 import expense kaynaklarını mevcut purchase invoice altyapısını tekrar kullanarak uygula.

## Kodlama öncesi blokaj

**[KARAR GEREKİYOR — A-125]** purchase_invoice kaynaklı import expense'in hangi non-stock service line modelinden geleceği kesinleşmeden faturalı expense kaynağı uygulanmaz.

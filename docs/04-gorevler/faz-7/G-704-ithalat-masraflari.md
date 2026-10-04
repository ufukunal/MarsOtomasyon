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
- K-257 faturalı import expense kaynağı `line_kind=service` document line olabilir; mixed faturada yalnız bu service line amount'u expense'e alınır.
- Manual source currency + frozen exchange_rate snapshot taşır.
- amount_base BCMath ile hesaplanır.
- other description zorunlu.
- indirilebilir import VAT inventory cost dışıdır.
- Import file cari hareket üretmez.
- Manual expense oluşturan state-changing Action K-038 gereği `idempotency_key` taşır; aynı anahtar retry edildiğinde ikinci import expense kaydı oluşmaz. Purchase-invoice kaynak seçimi mevcut posted kaydı referanslar, ikinci cari hareket üretmez.

## Kabul ölçütü

- Farklı currency expense'ler base currency'e frozen kurla çevriliyor.
- Manual expense cari hareket üretmiyor.
- Purchase invoice expense ikinci cari hareket üretmiyor.
- Mixed stock+service invoice'da stock line tutarları expense amount'a karışmıyor.
- other açıklamasız reddediliyor.
- inventory-cost flag doğru.
- Aynı idempotency key ile yinelenen manual expense create isteği tek expense kaydı bırakıyor.

## İstem

> K-116…K-119 import expense kaynaklarını mevcut purchase invoice altyapısını tekrar kullanarak uygula.

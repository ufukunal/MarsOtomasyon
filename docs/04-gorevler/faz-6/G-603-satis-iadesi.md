# G-603 — Satış iadesi posting

## Amaç

Satış iadesini customer credit + stock in + quarantine olarak atomik post etmek.

## Önkoşul

G-601, G-602, PostDocument, stok/cari çekirdeği.

## Dokunulacak dosyalar

- SalesReturn form/detail
- PostSalesReturn
- posting profile
- quarantine creation helper
- sales return tests

## Şema / Kod

Posting:

1. source/reason doğrula
2. quantity remaining doğrula
3. stock in reason=sales_return
4. unit_cost çöz
5. quarantine entry oluştur
6. stock_balances.quarantine artır
7. contact credit
8. verify/audit/idempotency

## Kurallar

- Kaynaklı maliyet original sales stock-out unit_cost.
- Kaynaksız maliyet current moving average.
- quantity +Q ve quarantine +Q birlikte.
- Kullanılabilir stok post anında artmaz.
- Otomatik cash/bank hareketi yok.

## Kabul ölçütü

- Stock quantity +Q.
- Quarantine +Q.
- Available değişmiyor.
- Customer credit doğru.
- Source cost doğru.
- Manual source moving average doğru.
- Duplicate post etkisi yok.

## İstem

> K-100/K-103/K-110 satış iadesini tek transaction'da uygula; quarantine'ı fiziksel stoktan ayrı sanma.

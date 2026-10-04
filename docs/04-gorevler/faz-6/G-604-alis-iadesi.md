# G-604 — Alış iadesi posting

## Amaç

Alış iadesini stock out + supplier debit olarak uygulamak ve K-104 maliyet seçimini desteklemek.

## Önkoşul

G-601, G-602, Faz 4 alış çekirdeği.

## Dokunulacak dosyalar

- PurchaseReturn form/detail
- PostPurchaseReturn
- cost basis resolver
- purchase return tests

## Şema / Kod

Cost basis:

- current_moving_average
- source_purchase_cost

Manual source => yalnız current_moving_average.

Dövizli source => original frozen exchange_rate.

## Kurallar

- Stock out moving average'ı değiştirmez.
- Supplier debit aynı transaction.
- Otomatik cash/bank yok.
- Kaynaklı fiyat/KDV snapshot immutable.
- source_purchase_cost seçeneği yalnız source varsa.
- `PostPurchaseReturn` K-038 gereği `idempotency_key` taşır; aynı anahtar tekrarlandığında ikinci stock out veya supplier debit üretilmez.

## Kabul ölçütü

- Her iki cost basis doğru unit_cost üretir.
- Manual source source_purchase_cost seçemiyor.
- Supplier debit doğru.
- Stock out doğru.
- Original frozen FX kullanılıyor.
- Moving average değişmiyor.
- Aynı idempotency key ile yinelenen alış iadesi tek posted return / tek stock out / tek supplier debit etkisi bırakıyor.

## İstem

> K-101/K-104/K-108 alış iadesini uygula; maliyet temelini kullanıcı seçimine göre çöz.

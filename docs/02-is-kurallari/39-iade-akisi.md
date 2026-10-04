# İade akışı

## Belge tipleri

- `sales_return`
- `purchase_return`

K-098 gereği ikisi de Faz 6 kapsamındadır.

## Kaynak modu

K-099:

- aynı dönem kaynaklı,
- önceki dönem kaynaklı,
- kaynaksız kontrollü manuel

iade desteklenir.

## Satış iadesi

K-100:

Posting aynı transaction içinde:

- customer `contact_transactions.credit`,
- `stock_movements.in, reason=sales_return`,
- aynı miktar `quarantine_entries.pending`,
- `stock_balances.quantity += Q`,
- `stock_balances.quarantine += Q`.

Sonuç:

```
available = quantity - reserved - consignment_reserved - quarantine
```

değişmez.

## Alış iadesi

K-101:

- `stock_movements.out, reason=purchase_return`,
- supplier `contact_transactions.debit`.

Karantina yoktur.

## İade ≠ reverse

İade gerçek ticari olaydır. Reverse, hatalı posted belgenin teknik/ticari ters kaydıdır.

Posted return immutable'dır; yanlış return ayrıca reverse edilir.

## Finans

K-105:

İade otomatik cash/bank hareketi üretmez.

- müşteri geri ödemesi ayrı finans işlemi,
- tedarikçiden para tahsilatı ayrı finans işlemi.

Cari bakiye yine contact_transactions toplamıdır.

## Yetki

- `returns.sales.post`
- `returns.purchase.post`
- `returns.manual_source`

Ayrı approval state yoktur.

## Neden

K-112/K-113 gereği her iade reason_code taşır. `other` seçildiyse açıklama zorunludur. Kaynaksız iadede ayrıca gerekçe/audit zorunludur.

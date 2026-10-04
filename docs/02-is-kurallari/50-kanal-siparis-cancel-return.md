# Kanal sipariş, cancel ve return

## Sipariş importu

K-179/K-180:

External order mevcut `sales_order` belgesine otomatik confirmed olarak import edilir.

K-181:

```
unique event = channel_account_id + external_order_id
```

Master channel_external_event_registry dönemler arası duplicate engelidir.

## Cari

K-182:

Her channel account için tek marketplace customer contact kullanılır. Bu eşleme period DB `channel_account_period_settings.marketplace_customer_contact_id` alanının gerçek kaynağıdır.

Gerçek alıcı bilgileri contact'a yazılmaz; channel_order_snapshots üzerinde frozen kalır.

## Para

K-183/K-184:

Sipariş importu:

- collection üretmez,
- cash/bank movement üretmez,
- payout/commission settlement ilk Faz 9 kapsamında değildir.

## Fiyat ve indirim

K-185/K-186:

- platform order line fiyatı frozen gerçektir,
- internal pricing ile yeniden hesaplanmaz,
- external discount değerleri existing sales-order discount alanlarına normalize edilir,
- gerekli campaign metadata snapshot tutulabilir.

## Shipping snapshot

K-187/K-188:

Buyer/recipient/address/cargo/package alanları channel_order_snapshots üzerinde tutulur.

Ayrı carrier API entegrasyonu Faz 9 ilk sürümünde yoktur.

## Cancel

K-189:

Henüz fulfill edilmemiş miktar:

- mevcut sales_order cancelled_quantity davranışını kullanır,
- rezerv varsa ilgili miktar çözülür.

Shipped/fulfilled miktar sessizce geri alınmaz.

## Return

K-190:

Marketplace return event:

- source channel metadata ile draft sales_return oluşturur,
- kullanıcı kontrol/post eder,
- Faz 6 quarantine ve cari/stok kuralları aynen çalışır.

## Shipment status

K-191:

Destekleyen adapter'larda sevk/kargo durumları platforma push edilir.

Platform-specific state mapping adapter sorumluluğudur.

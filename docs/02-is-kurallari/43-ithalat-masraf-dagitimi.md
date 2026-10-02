# İthalat masraf dağıtımı

## Yöntemler

K-115/K-129:

- purchase_value
- quantity
- manual

Ağırlık/hacim yoktur.

## Purchase value dağıtımı

Her satırın base currency alış değeri kullanılır:

```
ratio = line.purchase_value_base / total_purchase_value_base
allocation = expense.amount_base × ratio
```

## Quantity dağıtımı

Temel birim quantity kullanılır:

```
ratio = line.base_quantity / total_base_quantity
allocation = expense.amount_base × ratio
```

## Manual

Kullanıcı satır bazında base currency allocation girer.

Finalize öncesi:

```
SUM(manual allocations) = expense.amount_base
```

zorunlu.

## Döviz

K-119:

- her kaynak belge kendi frozen kuru,
- manual expense kendi frozen exchange_rate snapshot'ı,
- tüm dağıtım şirket base currency üzerinde yapılır,
- kur farkı hesabı yoktur.

## Yuvarlama

K-128:

- BCMath/Money,
- ara hesap yeterli scale,
- normalize edilen toplam ile expense.amount_base farkı deterministik son uygun satıra verilir,
- dağıtılmamış remainder kalmaz.

## Değişiklik

Finalized dosyada allocation mutate edilmez.

Sonradan masraf K-122 ile ayrı cost adjustment akışıdır.

## Integrity

`integrity:imports`:

- allocation toplamı,
- allocation base doğruluğu,
- line membership,
- expense inventory-cost flag,
- rounding remainder

kontrollerini yapar.

# G-705 — İthalat masraf dağıtımı

## Amaç

Import expenses tutarlarını purchase value, quantity veya manual yöntemle file satırlarına dağıtmak.

## Önkoşul

G-703, G-704.

## Dokunulacak dosyalar

- ImportCostAllocator
- allocation UI
- allocation persistence
- allocation unit/feature tests

## Şema / Kod

Yöntemler:

- purchase_value
- quantity
- manual

K-129 gereği weight/volume yok.

## Kurallar

- Tüm hesap string + BCMath/Money.
- Auto allocation base currency.
- Manual toplam expense.amount_base'a eşit olmalı.
- Rounding remainder K-128 ile son uygun satıra.
- inventory-cost=false expense maliyet dağıtımına girmez.

## Kabul ölçütü

- Purchase value oranı elle hesapla eşleşiyor.
- Quantity base_quantity kullanıyor.
- Manual eksik/fazla toplam reddediliyor.
- Rounding sonrası toplam birebir expense amount.
- Weight/volume method yok.
- Finalized allocation değişmiyor.

## İstem

> K-115/K-128/K-129 masraf dağıtım motorunu deterministik ve BCMath tabanlı uygula.

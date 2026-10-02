# Ekran — İthalat Masraf Dağıtımı

## Amaç

Her ithalat masrafını K-115 yöntemlerinden biriyle import file satırlarına dağıtmak.

## Yöntem

- Alış Değerine Göre
- Miktara Göre
- Manuel

Ağırlık/hacim yoktur.

## Tablo

- Ürün
- Base Quantity
- Purchase Value Base
- Allocation Base
- Dağıtılan Tutar
- Final Import Total Cost
- Final Import Unit Cost

## Manual

Kullanıcı satır dağıtım tutarlarını girer.

Toplam, expense base amount'a eşit değilse finalize edilemez.

## Yuvarlama

Otomatik yöntemlerde kalan fark deterministik son uygun satıra verilir.

UI toplamları:

- Expense Total
- Allocated Total
- Difference

gösterir.

Difference finalize öncesi 0 olmalıdır.

# Ekran — İthalat Maliyet Adjustment

## Amaç

Finalized ithalat dosyasına sonradan gelen ek maliyeti eski dosyayı değiştirmeden dağıtmak.

## Kaynak

- Finalized import file
- Yeni masraf kayıtları

## Masraf

K-116/K-117/K-118 kuralları aynen kullanılır.

## Dağıtım

- alış değeri
- miktar
- manuel

## Önizleme

Ürün bazında:

- mevcut moving average
- quantity basis
- ek maliyet
- unit adjustment
- projected moving average

## Post

- eski import file immutable kalır
- inventory_cost_adjustments yazılır
- product_costs güncellenir
- import file görünür durumu adjusted olur

## Reverse

Adjustment yerinde değiştirilmez; ters adjustment oluşturulur.

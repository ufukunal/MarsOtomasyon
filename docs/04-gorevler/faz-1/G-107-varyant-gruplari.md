# G-107 — Varyant grupları

## Amaç
Ayrı kartları tek ürün gibi göstermek. B2B ve kendi e-ticaret sitelerinde
grup tek ürün, kartlar varyant olarak yayınlanır.

## Şemalar
```php
// variant_groups:         id, company_id, name, is_active
// variant_attributes:     id, variant_group_id, name(Renk,Ölçü), sort_order
// product_variant_values: id, product_id, variant_attribute_id, value
//   unique(product_id, variant_attribute_id)
```

## Ekran — Varyant Grubu Detayı
Üstte grup adı ve özellikler (Renk, Ölçü).
Altta gruba bağlı ürün kartları tablosu: kod, ad, her özellik için değer,
fiyat, stok, durum. "Ürün ekle" ile mevcut kart gruba bağlanır.

## Kurallar
- Bir ürün en fazla bir gruba bağlı
- Grup içinde aynı özellik kombinasyonu iki kez olamaz (uyarı)
- Grup silinince ürünler silinmez, bağ kalkar
- Grupta en az iki ürün olmalı (tek ürünlü grup anlamsız, uyarı)

## Kabul ölçütü
- Üç kart bir gruba bağlanıyor, özellik değerleri giriliyor
- Aynı kombinasyon ikinci kez uyarı veriyor
- Grup silinince kartlar duruyor

## İstem
> variant_groups, variant_attributes ve product_variant_values tabloları için
> migration, modeller ve Varyant Grubu Detay ekranını yaz. Bir ürün en fazla
> bir gruba bağlansın. Aynı özellik kombinasyonunu uyar.

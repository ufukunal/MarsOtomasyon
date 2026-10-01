# G-107 — Varyant grupları

**Veritabanı: DÖNEM.** Model `PeriodModel`'den türer, migration
`database/migrations/period/` altına yazılır. `company_id` kolonu YOKTUR.


## Önkoşul
G-106 (ürün kartı)

## Dokunulacak dosyalar
- `database/migrations/period/`


## Şema / Kod

Bu bölümdeki mevcut şema örnekleri aşağıdaki kanonik mimari kurallarla birlikte uygulanır. Çelişkide kanonik kurallar üstündür.

## Amaç
Ayrı kartları tek ürün gibi göstermek. B2B ve kendi e-ticaret sitelerinde
grup tek ürün, kartlar varyant olarak yayınlanır.

## Şemalar
```php
// variant_groups:         id, name, is_active, version
// variant_attributes:     id, variant_group_id, name(Renk,Ölçü), sort_order, version
// product_variant_values: id, product_id, variant_attribute_id, value, version
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


### Göreve özel kararlar
- Varyant grubu yalnız B2B/kendi site sunumudur; pazaryerlerine her kart ayrı ürün gider.


### Uygulama ayrıntıları
- Varyant grubu period DB'dedir ve product kartlarını B2B/kendi site sunumu için bir araya getirir.
- Pazaryerine varyant grubu gönderilmez; her product kartı bağımsız ürün olarak eşlenir.
- Varyant özelliği ürün kimliğinin yerine geçmez; her kombinasyon ayrı product kaydıdır.
- Dönem devrinde grup/özellik ilişkileri kimlik sürekliliğini korur.

## Kabul ölçütü
- Üç kart bir gruba bağlanıyor, özellik değerleri giriliyor
- Aynı kombinasyon ikinci kez uyarı veriyor
- Grup pasife/alakasız hale getirildiğinde kartlar duruyor; ürün kartları silinmiyor
- Stale `version` ile grup/özellik güncellemesi reddediliyor


## İstem
> variant_groups, variant_attributes ve product_variant_values tabloları için
> migration, modeller ve Varyant Grubu Detay ekranını yaz. Bir ürün en fazla
> bir gruba bağlansın. Aynı özellik kombinasyonunu uyar. Düzenlenebilir varyant kayıtlarını `version` optimistic lock ile koru.

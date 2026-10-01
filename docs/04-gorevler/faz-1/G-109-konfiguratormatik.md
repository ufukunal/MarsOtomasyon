# G-109 — Konfigüratör tanımları

**Veritabanı: DÖNEM.** Model `PeriodModel`'den türer, migration
`database/migrations/period/` altına yazılır. `company_id` kolonu YOKTUR.


## Amaç
Müşterinin gövde + kristal + duy seçerek ürün oluşturmasını sağlayan tanımlar.
Sipariş satırında kullanımı Faz 3'te gelir.

## Şemalar
```php
// config_definitions: id, company_id, product_id, name, is_required(bool), sort_order
// config_options:     id, config_definition_id, component_product_id,
//                     label, sort_order, is_default(bool)
//   FİYAT FARKI YOK — konfigüratör yalnız özellik tanımlar (A-002)
```

## Ekran
Ürün formunda tip `configurable` seçilince "Konfigürasyon" sekmesi açılır.
Seçim grupları (Gövde, Kristal, Duy) ve her grubun seçenekleri.
Her seçenek bir bileşen ürüne ve fiyat farkına bağlanır.

## Kurallar
- Zorunlu grupta en az bir seçenek olmalı
- Her grupta en fazla bir varsayılan seçenek
- Bileşen ürün pasifse seçenek de seçilemez
- Fiyat alanı **yoktur**; konfigüratör fiyatı etkilemez

## Faz 3 bağlantısı
Sipariş satırında seçim **dondurulur** (JSON olarak satırda saklanır);
tanım sonradan değişse eski sipariş bozulmaz.

## Kabul ölçütü
- Üç gruplu konfigürasyon tanımlanıyor
- Zorunlu grupta seçenek yoksa kayıt reddediliyor
- Seçimler sipariş satırına bilgi olarak yazılıyor

## İstem
> config_definitions ve config_options tabloları için migration, modeller ve
> ürün formundaki Konfigürasyon sekmesini yaz. Zorunlu grupta en az bir
> seçenek kuralını uygula.

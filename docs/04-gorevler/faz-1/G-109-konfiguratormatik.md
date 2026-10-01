# G-109 — Konfigüratör tanımları

**Veritabanı: DÖNEM.** Model `PeriodModel`'den türer, migration
`database/migrations/period/` altına yazılır. `company_id` kolonu YOKTUR.


## Önkoşul
G-106 (ürün kartı)

## Dokunulacak dosyalar
- `database/migrations/period/`


## Şema / Kod

Bu bölümdeki mevcut şema örnekleri aşağıdaki kanonik mimari kurallarla birlikte uygulanır. Çelişkide kanonik kurallar üstündür.

## Amaç
Müşterinin gövde + kristal + duy seçerek ürün oluşturmasını sağlayan tanımlar.
Sipariş satırında kullanımı Faz 3'te gelir.

## Şemalar
```php
// config_definitions: id, product_id, name, is_required(bool), sort_order, version
// config_options:     id, config_definition_id, component_product_id,
//                     label, sort_order, is_default(bool), version
//   FİYAT FARKI YOK — konfigüratör yalnız özellik tanımlar (A-002)
```

## Ekran
Ürün formunda tip `configurable` seçilince "Konfigürasyon" sekmesi açılır.
Seçim grupları (Gövde, Kristal, Duy) ve her grubun seçenekleri.
Her seçenek gerekiyorsa bir bileşen ürüne bağlanır. **Fiyat/fiyat farkı alanı yoktur.**

## Kurallar
- Zorunlu grupta en az bir seçenek olmalı
- Her grupta en fazla bir varsayılan seçenek
- Bileşen ürün pasifse seçenek de seçilemez
- Fiyat alanı **yoktur**; konfigüratör fiyatı etkilemez

## Faz 3 bağlantısı
Sipariş satırında seçim **dondurulur** (JSON olarak satırda saklanır);
tanım sonradan değişse eski sipariş bozulmaz.


### Göreve özel kararlar
- Konfigüratör fiyatı etkilemez; option üzerinde fiyat alanı yoktur.
- Satış satırında seçimler snapshot/dondurulmuş veri olarak saklanır.


### Uygulama ayrıntıları
- Konfigüratör period DB'dedir ve ürünün özellik seçimlerini tanımlar; fiyat hesaplamaz.
- Option kayıtlarında fiyat alanı bulunmaz; satış fiyatı normal fiyat çözümleme zincirinden gelir.
- Belge satırında seçilen konfigürasyon daha sonra değişmemesi için snapshot olarak dondurulabilir.
- Dönem devrinde config definition/option kimlikleri ve ilişkileri korunur.

## Kabul ölçütü
- Üç gruplu konfigürasyon tanımlanıyor
- Zorunlu grupta seçenek yoksa kayıt reddediliyor
- Seçimler sipariş satırına bilgi olarak yazılıyor
- Şemada option price/price_delta alanı bulunmuyor
- Stale `version` ile definition/option güncellemesi reddediliyor


## İstem
> config_definitions ve config_options tabloları için migration, modeller ve
> ürün formundaki Konfigürasyon sekmesini yaz. Zorunlu grupta en az bir
> seçenek kuralını uygula. Fiyat/fiyat farkı alanı ekleme ve düzenlenebilir kayıtları `version` optimistic lock ile koru.

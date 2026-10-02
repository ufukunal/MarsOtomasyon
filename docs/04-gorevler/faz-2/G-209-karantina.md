# G-209 — Karantina

**Veritabanı: DÖNEM.** Model `PeriodModel`'den türer, `company_id` kolonu
yoktur, migration `database/migrations/period/` altına yazılır.


## Amaç
İade edilen veya kontrol bekleyen malın satılamaz durumda tutulması.

## Önkoşul
G-202


## Dokunulacak dosyalar
- `database/migrations/period/`


## Şema / Kod

Mevcut şema/kod örnekleri aşağıdaki kanonik stok sözleşmesiyle birlikte uygulanır; çelişkide kanonik sözleşme üstündür.

## Model
Ayrı tablo yok. `stock_balances.quarantine` alanı kullanılır; giriş ve
çıkışlar `quarantine_movements` görünümüyle izlenir.

```php
// quarantine_entries: id, product_id, location_id,
//   quantity decimal(18,3), unit_cost decimal(18,4),
//   source_document_type, source_document_id,
//   status(pending|released|scrapped),
//   decided_by, decided_at, decision_note, created_at
```

## Akış

```
İade geldi
 → RecordStockMovement(in, reason=return) ile stock_balances.quantity += miktar
 → quarantine_entries satırı (status = pending)
 → stock_balances.quarantine += miktar
 → kullanılabilir stok değişmez; fiziksel stok ve quarantine birlikte artar

Kontrol sonucu:
 (a) Satılabilir → quarantine -= miktar, status = released
     stok normal kullanılabilir hale gelir, hareket YAZILMAZ
 (b) Hurda      → quarantine -= miktar, status = scrapped
     RecordStockMovement(out, reason=scrap) çağrılır, stok düşer
```

## Kurallar
- Karantinadaki mal **satılamaz, rezerve edilemez, transfer edilemez**
- Kullanılabilir hesabından düşülür
- K-102 gereği karar kısmi olabilir; `released + scrapped <= quantity`, kalan miktar pending'dir
- Karar `activity_log`'a düşer

## Ekran
Liste: ürün, lokasyon, miktar, kaynak belge, bekleme süresi (gün), durum.
Eylem: seçili satırlar için "Satılabilir" / "Hurda" toplu karar.
Uyarı: 30 günden uzun bekleyen satırlar işaretlenir.


### Göreve özel kararlar
- İade karantina kaydı normal kullanılabilir stoğa otomatik dönmez.
- Karar satılabilir/hurda sonrası uygun hareket/alan çözümü yapılır.


### Uygulama ayrıntıları
- Karantina miktarı fiziksel stok içinde olabilir ama kullanılabilir/rezerve edilebilir stoktan düşülür.
- Satış iadesi karantinaya alındığında doğrudan satılabilir stoğa dönmez.
- Karantinadan çıkış kararı ayrı iş eylemidir; satışa uygun/hurda vb. sonuç ilgili stok hareketini üretir.
- Dönem sonunda açık karantina kaydı varsa K-109 ve dönem devri kurallarıyla uyumlu şekilde yeni döneme açık miktar/snapshot taşınır; sessizce düşürülmez.

## Kabul ölçütü
- Satış iadesinde fiziksel stok quantity ve quarantine aynı miktarda artıyor; kullanılabilir stok değişmiyor
- Satılabilir kararında hareket oluşmuyor, kullanılabilir artıyor
- Hurda kararında çıkış hareketi oluşuyor
- Karantinadaki ürün satış belgesinde seçilemiyor


## İstem
> quarantine_entries tablosu için migration, model, karantina ekranı ve
> karar action'larını yaz. Satılabilir kararında stok hareketi OLUŞTURMA,
> yalnızca quarantine alanını azalt. Hurda kararında RecordStockMovement
> çağır.

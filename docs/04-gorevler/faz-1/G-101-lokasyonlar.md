# G-101 — Lokasyonlar

**Veritabanı: DÖNEM.** Model `PeriodModel`'den türer, migration
`database/migrations/period/` altına yazılır. `company_id` kolonu YOKTUR.


## Önkoşul
G-0b2 (tablo bileşeni)

## Dokunulacak dosyalar
- `database/migrations/period/*_create_locations_table.php`
- `app/Models/Period/Location.php`
- `app/Enums/LocationKind.php`
- location list/form Livewire + blade
- location policy/validation Action'ları
- `tests/Feature/Locations/LocationCrudTest.php`

## Amaç
Stok tutulan yerler: **depo, şube, araç**. Araç sıcak satışta kullanılır.

## Şema / Kod
`docs/01-veri-modeli/12-locations.md` içindeki şemayı birebir uygula.

## Ekran
Liste: kod, ad, tip (rozet), plaka (araçsa), varsayılan, durum.
Form: kod, ad, tip seçimi, araçsa plaka alanı açılır, adres, varsayılan, durum.

## Kurallar
- Her şirkette en az bir depo ve bir varsayılan lokasyon olmalı
- Varsayılan lokasyon silinemez
- Hareketi olan lokasyon silinemez, pasife alınır
- Araç lokasyonu depo gibi davranır; ayrı mantık yoktur


### Göreve özel kararlar
- Faz 1 lokasyon tipleri warehouse|branch|vehicle; araç normal stok lokasyonu gibi davranır. Faz 8 G-801 `subcontractor` kind değerini bu tabloya sonradan ekler.
- Satış satırı ileride lokasyon taşıyabilir; rezervasyon motoru bir satırı birden fazla lokasyona dağıtabilir.


### Uygulama ayrıntıları
- `locations` period DB'dedir; `warehouse|branch|vehicle` tipleri kullanılır.
- Lokasyon kodu period içinde benzersizdir ve pasifleşse bile tekrar kullanılmaz.
- Aynı şirket dönem devrinde lokasyon ID ve kodları korunur.
- Araç lokasyonu normal stok lokasyonu gibi bakiye tutar; sıcak satış ileride bu lokasyondan çıkar.

## Kabul ölçütü
- Üç tip de açılabiliyor, araçta plaka alanı çıkıyor
- Varsayılan işaretlenince eskisi kalkıyor
- Farklı şirketin lokasyonu görünmüyor


## İstem
> locations tablosu için migration, Location modeli, LocationKind enum'u,
> liste ve form ekranlarını yaz. Tip vehicle seçilince plaka alanı görünsün.
> Varsayılan lokasyon tekil olsun.

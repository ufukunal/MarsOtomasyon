# G-105 — Cari yan tabloları

**Veritabanı: DÖNEM.** Model `PeriodModel`'den türer, migration
`database/migrations/period/` altına yazılır. `company_id` kolonu YOKTUR.


## Amaç
Cari kartının adres, yetkili, banka ve kategori bilgileri.

## Şemalar
`docs/01-veri-modeli/10-contacts.md` içindeki `contact_addresses`,
`contact_people`, `contact_banks`, `contact_categories` şemalarını birebir uygula.

## Ekranlar
Cari detayında sekme olarak: **Adresler**, **İletişim**, **Banka Bilgileri**.
Her biri satır ekle/düzenle/sil yapabilen küçük tablo.

## Kurallar
- Her türde (fatura/sevk) en fazla bir varsayılan adres
- Varsayılan yetkili tekil
- IBAN biçim doğrulaması (TR + 24 hane)
- Cari silinince yan kayıtlar da silinir (cascade)

## Kabul ölçütü
- Varsayılan adres işaretlenince eskisi kalkıyor
- Geçersiz IBAN reddediliyor
- Farklı şirketin carisine satır eklenemiyor

## İstem
> contact_addresses, contact_people, contact_banks ve contact_categories
> tabloları için migration, modeller ve cari detayındaki üç sekmeyi yaz.
> IBAN doğrulaması ekle. Varsayılan kayıtlar tekil olsun.

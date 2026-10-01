# G-105 — Cari yan tabloları

**Veritabanı: DÖNEM.** Model `PeriodModel`'den türer, migration
`database/migrations/period/` altına yazılır. `company_id` kolonu YOKTUR.


## Önkoşul
G-104 (cari kartı)

## Dokunulacak dosyalar
- `database/migrations/period/`


## Şema / Kod

Bu bölümdeki mevcut şema örnekleri aşağıdaki kanonik mimari kurallarla birlikte uygulanır. Çelişkide kanonik kurallar üstündür.

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


### Göreve özel kararlar
- Adres/yetkili/banka yan tabloları contact_id gerçek period FK kullanır.
- TC kimlik tam görünüm ayrı izinle korunur.


### Uygulama ayrıntıları
- Adres, yetkili/kişi ve banka yan tabloları aynı period içindeki `contacts.id` alanına gerçek FK ile bağlanır.
- Yan tablolar şirket global scope kullanmaz; aktif period bağlantısı yeterlidir.
- Hassas kişisel alanlar yalnız görevde/kararda tanımlandığı ölçüde tutulur; TC tam görünümü ayrı izinle korunur.
- Dönem devrinde cari ile birlikte gerekli yan kayıtlar da taşınır.

## Kabul ölçütü
- Varsayılan adres işaretlenince eskisi kalkıyor
- Geçersiz IBAN reddediliyor
- Farklı şirketin carisine satır eklenemiyor


## İstem
> contact_addresses, contact_people, contact_banks ve contact_categories
> tabloları için migration, modeller ve cari detayındaki üç sekmeyi yaz.
> IBAN doğrulaması ekle. Varsayılan kayıtlar tekil olsun.

# Veri modeli — genel kurallar

Her tablo dosyası aynı biçimdedir: amaç, şema, kısıtlar, ilişkiler, örnek veri.

## Tüm iş tablolarında zorunlu

| Kolon | Tip | Not |
|---|---|---|
| `id` | bigIncrements | |
| `company_id` | foreignId → companies | **Global scope buradan çalışır** |
| `created_at` / `updated_at` | timestamps | |

İstisna: `companies`, `users`, `roles`, `permissions` — bunlar sistem
tablolarıdır, `company_id` taşımaz.

## Silme politikası

İş kayıtları **silinmez**. `deleted_at` (softDeletes) yalnızca kart
tablolarında (cari, ürün) bulunur; belgelerde bile yoktur — belge iptal
edilir, ters kayıt yazılır.

## Sayısal tipler

Tutar `decimal(18,4)`, miktar `decimal(18,3)`, oran `decimal(7,4)`,
kur `decimal(18,6)`. **Float yasak.**

## İndeks kuralı

Her yabancı anahtar indekslenir. Sık filtrelenen kolonlar
(`company_id + is_active`, `company_id + code`) bileşik indeks alır.

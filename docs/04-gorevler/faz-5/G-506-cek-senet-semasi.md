# G-506 — Çek/Senet veri modeli

## Amaç

K-096/K-097 için security kimliği, geniş operasyon alanları ve immutable event geçmişini kurmak.

## Önkoşul

G-501, contact_transactions ve bank account altyapısı.

## Dokunulacak dosyalar

- securities migration/model
- security_events migration/model
- SecurityType/Direction/Status/EventType enum/sözleşmeleri
- security schema testleri

## Şema / Kod

Kanonik kaynak:

- `docs/01-veri-modeli/38-securities.md`

`securities` kıymet kimliği + current status snapshot taşır.

`security_events` immutable lifecycle geçmişidir ve gerektiğinde contact/cash/bank movement referanslarını taşır.

## Kurallar

- Period tablolarında company_id yok.
- Event silinmez/mutate edilmez.
- current_status event geçmişinden türetilir ve integrity ile doğrulanır.
- Ciro target contact event üzerinde tutulur.
- Banka operasyon alanları K-097 kapsamındadır.
- Master actor'a cross-DB FK yok.

## Kabul ölçütü

- Migration PostgreSQL up/down çalışıyor.
- Received/issued direction ve status enum doğrulamaları çalışıyor.
- Event FK'leri doğru.
- Ciro event counterparty contact saklayabiliyor.
- Bank account/reference/return metadata saklanabiliyor.
- Event history silme/mutate uygulama katmanında engelleniyor.

## İstem

> Veri modeli 38'e göre securities + security_events kur. Kıymet history'sini tek mutable status kaydına indirgeme.

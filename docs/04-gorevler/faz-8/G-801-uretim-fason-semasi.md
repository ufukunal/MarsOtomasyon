# G-801 — Üretim/fason veri modeli

## Amaç

Reçete, production order, component snapshot, completion, consumption, output ve subcontractor location veri modelini kurmak.

## Önkoşul

Faz 2 stok, Faz 4 alış, Faz 7 inventory cost adjustment, K-131…K-162.

## Dokunulacak dosyalar

- production recipe/order/completion migrations/models
- locations kind/subcontractor_contact_id genişletmesi
- production_service_invoices mapping migration/model
- production_service_allocations migration/model
- inventory_cost_adjustments production_completion_id genişletmesi
- schema testleri

## Şema / Kod

Kanonik kaynak:

- `docs/01-veri-modeli/41-production-and-subcontracting.md`

Fason hizmet bağı:

- production_service_invoices

## Kurallar

- Period tablolarda company_id yok.
- Fire yüzde alanı yok.
- Output çoklu location destekler.
- Subcontractor location normal sales/reservation için uygun değildir.
- Production completion immutable.

## Kabul ölçütü

- PostgreSQL migration up/down.
- Recipe revision unique kuralları çalışıyor.
- Completion outputs toplamı completion quantity ile doğrulanabiliyor.
- Subcontractor location contact ilişkisi çalışıyor.
- Production completion reversal FK/history destekleniyor.

## İstem

> Faz 8 üretim/fason veri modelini 41-production-and-subcontracting.md'ye göre uygula.

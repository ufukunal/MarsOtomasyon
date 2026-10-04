# G-702 — İthalat dosyası yaşam döngüsü

## Amaç

K-126 yaşam döngüsünü ve immutable finalize sözleşmesini uygulamak.

## Önkoşul

G-701.

## Dokunulacak dosyalar

- ImportFile liste/detay Livewire
- state transition Action'ları
- number series
- lifecycle feature testleri

## Şema / Kod

Durum:

- draft
- cost_collection
- finalized
- adjusted

Geçiş:

```
draft → cost_collection → finalized
finalized → adjusted
```

Adjusted, late-cost adjustment varlığını gösteren türetilmiş durumdur.

## Kurallar

- Finalized tekrar draft/cost_collection yapılamaz.
- Ayrı approval state yok.
- document_date period lock'a tabi.
- finalize idempotent.
- version optimistic lock.

## Kabul ölçütü

- İzinli geçişler çalışıyor.
- Illegal transition reddediliyor.
- Finalized mutate edilemiyor.
- Duplicate finalize reddediliyor.
- Audit actor/status before-after taşıyor.

## İstem

> K-121/K-126 ithalat lifecycle'ını immutable finalize ile uygula.

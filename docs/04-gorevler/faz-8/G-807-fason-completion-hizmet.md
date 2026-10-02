# G-807 — Fason completion ve hizmet maliyeti

## Amaç

Fason location component tüketimi, kısmi completion, fason hizmet purchase_invoice maliyeti ve late service cost adjustment akışını uygulamak.

## Önkoşul

G-804…G-806, Faz 4 purchase_invoice, Faz 7 inventory_cost_adjustments.

## Dokunulacak dosyalar

- subcontract completion UI/Action
- subcontract_service_source relation
- service cost allocator
- late cost adjustment integration
- fason tests

## Şema / Kod

Hizmet faturası:

- normal purchase_invoice
- supplier=production_order subcontractor
- relation=subcontract_service_source

Late cost:

- inventory_cost_adjustments
- reason=subcontract_late_cost
- original completion quantity basis

## Kurallar

- Completion fatura olmadan yapılabilir.
- Posted bağlı hizmet faturası varsa production cost'a dahil.
- Fason fire mamul maliyetine dahil.
- Kısmi completion sonrası kalan component subcontractor location'da kalabilir.
- Late cost physical stock quantity değiştirmez.

## Kabul ölçütü

- Fason location'dan consumption/fire doğru.
- Hizmet faturası bağlıysa service cost production unit cost'a giriyor.
- Fatura yoksa completion mümkün.
- Sonradan fatura late cost adjustment oluşturuyor.
- Geçmiş stock movement maliyetleri mutate edilmiyor.
- Multi-location finished output çalışıyor.

## İstem

> K-151…K-157 fason completion ve service-cost zincirini mevcut purchase_invoice + inventory_cost_adjustments altyapısıyla uygula.

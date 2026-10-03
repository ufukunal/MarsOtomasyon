# G-807 — Fason completion ve hizmet maliyeti

## Amaç

Fason location component tüketimi, kısmi completion, fason hizmet purchase_invoice maliyeti ve late service cost adjustment akışını uygulamak.

## Önkoşul

G-804…G-806, Faz 4 purchase_invoice, Faz 7 inventory_cost_adjustments.

## Dokunulacak dosyalar

- subcontract completion UI/Action
- production_service_invoices mapping
- production_service_allocations migration/model
- service cost allocator
- late cost adjustment integration
- fason tests

## Şema / Kod

Hizmet faturası:

- normal purchase_invoice
- supplier=production_order subcontractor
- production_service_invoices ile production_order bağlantısı

Service allocation:

- K-258 completed_quantity-oranlı dağıtım
- `production_service_allocations` gerçek kaydı
- kaynak satır K-257 `line_kind=service`
- rounding remainder son uygun completion

Late cost:

- inventory_cost_adjustments
- reason=subcontract_late_cost
- allocation'ın production_completion provenance'ı + original completion quantity basis

## Kurallar

- Completion fatura olmadan yapılabilir.
- Posted bağlı hizmet faturası varsa yalnız K-258 allocation payı production cost'a dahil.
- Fason fire mamul maliyetine dahil.
- Kısmi completion sonrası kalan component subcontractor location'da kalabilir.
- Late cost physical stock quantity değiştirmez.

## Kabul ölçütü

- Fason location'dan consumption/fire doğru.
- Hizmet faturası bağlıysa quantity-oranlı service allocation production unit cost'a giriyor.
- Birden fazla completion'da allocation toplamı service line amount_base ile eşleşiyor; rounding farkı son uygun completion'a gidiyor.
- Fatura yoksa completion mümkün.
- Sonradan fatura late cost adjustment oluşturuyor.
- Geçmiş stock movement maliyetleri mutate edilmiyor.
- Multi-location finished output çalışıyor.

## İstem

> K-151…K-157 ile K-257/K-258'e göre fason completion ve service-cost zincirini mevcut purchase_invoice + inventory_cost_adjustments altyapısıyla uygula. Service kaynağı yalnız `line_kind=service`; completion dağıtımı `production_service_allocations` gerçek kaydıdır.

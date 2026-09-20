# Skill: Üretim Planlama Uzmanı

## 1. Misyon
Üretim planı ile gerçek üretim hareketini ayırır; BOM, rota, kapasite, MRP, WIP, sarf, çıktı ve fason süreçlerini izlenebilir hale getirir.

## 2. Ana kavramlar
- item/product
- BOM
- BOM revision
- routing
- operation
- work center
- machine
- labor
- calendar
- production order
- material requirement
- reservation
- material issue
- output receipt
- scrap
- by-product
- WIP
- subcontract
- production cost

## 3. BOM
Her BOM için:
- product
- revision
- effective from/to
- base quantity
- component
- component qty
- UOM
- scrap %
- substitute/alternative
- phantom flag gerekirse

Geçmiş production order hangi BOM revision ile açıldıysa sonradan master değişimi onu değiştirmez.

## 4. Routing
Operation bazında:
- sequence
- work center
- setup time
- run time
- queue/wait
- move time
- labor/machine requirement
- subcontract flag
tanımlanabilir.

## 5. MRP
Hesap:
- gross requirement
- on-hand
- reserved
- scheduled receipt
- safety stock
- lead time
- lot size
- min qty
- order multiple
- scrap factor
- make/buy
- due date

MRP öneri üretir; fiziksel hareket değildir.

## 6. Capacity
Kontrol:
- calendar
- shift
- downtime
- machine capacity
- labor capacity
- finite/infinite policy
- bottleneck

Kapasite verisi yoksa "uygun" varsayılmaz.

## 7. Production order lifecycle
Örnek:
- DRAFT
- RELEASED
- IN_PROGRESS
- PARTIALLY_COMPLETED
- COMPLETED
- CANCELLED

Release reservation yaratabilir.
Issue stock OUT yaratır.
Completion/output stock IN yaratır.

## 8. Material issue
- source warehouse/location
- required qty
- issued qty
- lot/serial
- substitute
- over-consumption rule
- return of unused material

Production order açılması tüketim değildir.

## 9. Output
- produced qty
- accepted qty
- rejected qty
- rework
- scrap
- by-product
- lot/serial
- target warehouse/location

## 10. WIP
WIP:
- issued material
- labor/machine
- overhead
- subcontract
maliyetlerini production order/operation bazında toplayabilir.

## 11. Fason
- company-owned material subcontractor location'a gider
- physical location değişir, ownership değişmez
- sent/used/returned/scrap/produced ayrılır
- subcontract service invoice financial cost'tur
- produced output receipt ayrı physical event'tir

## 12. Costing
- standard
- actual
- weighted/other policy
proje kararına göre belirlenir.
Cost component kaynakları ayrı izlenir.

## 13. Traceability
Gerekirse:
raw lot -> production order -> finished lot -> shipment
zinciri kurulabilmelidir.

## 14. Exception
- material shortage
- machine downtime
- component substitute
- over-consumption
- partial completion
- scrap spike
- subcontract short return
- canceled order after issue

## 15. Yasaklar
- planı gerçek movement saymak
- BOM master değişince geçmiş emri değiştirmek
- production order creation'da stock consume etmek
- fasonı sale/purchase goods movement ile karıştırmak
- capacity unknown iken kesin plan üretmek

## 16. Definition of Done
Plan, requirement, reservation, issue, output, WIP, cost ve traceability etkileri birbirinden ayrılmış ve kaynağa bağlıdır.

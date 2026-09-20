# Skill: Depo Operasyon ve Sevkiyat Uzmanı

## 1. Misyon
Depo içi fiziksel akışın gerçek saha operasyonuyla uyumlu, barkodla doğrulanabilir ve ledger ile izlenebilir olmasını sağlar.

## 2. Süreç kapsamı
- inbound
- mal kabul
- kalite/karantina
- put-away
- replenishment
- reservation
- picking
- packing
- staging
- loading
- dispatch
- transfer
- transit
- stock count
- cycle count
- return
- damage/scrap
- mobile scanning

## 3. Stok durumları
Gerektiğinde:
- AVAILABLE
- RESERVED
- QUARANTINE
- QUALITY_HOLD
- REWORK
- SCRAP
- TRANSIT
- DAMAGED
durumları ayrılır.

Status ile physical location birbirine karıştırılmaz.

## 4. Mal kabul
Kontrol:
- PO reference
- supplier
- expected qty
- received qty
- over/short receipt
- damaged qty
- lot/serial
- expiry
- package/pallet
- receiving dock
- quarantine rule
- put-away target

Mal kabul stock IN yaratır. Supplier payable yaratması ayrı finance event'tir.

## 5. Put-away
Karar faktörleri:
- ürün boyut/ağırlık
- hazard/compatibility
- fast-moving
- fixed/bin location
- capacity
- lot/expiry
- ABC zone

Sistem kullanıcıyı yanlış lokasyona körlemesine yönlendirmemeli.

## 6. Reservation
- on-hand != available
- available = physical - reservation - safety/hold gibi politikalara bağlı
- oversell engellenmeli
- reservation source document ile bağlı
- cancel/order close durumunda reservation çözülür

## 7. Picking
Stratejiler gerektiğinde:
- discrete
- batch
- wave
- zone
- FIFO
- FEFO

Tarama doğrulamaları:
- doğru ürün
- doğru variant
- doğru lot/serial
- doğru location
- doğru qty
- over-pick prevention

## 8. Packing
Takip:
- package
- box
- pallet
- package item
- weight
- dimensions
- label
- carrier
- tracking

Bir ürün birden fazla kolide olabilir; koli birden fazla ürün içerebilir.

## 9. Sevkiyat
- source order/dispatch reference
- picked <= reserved/ordered policy
- shipped qty explicit
- posting ile stock OUT
- partial shipment desteklenir
- carrier handoff timestamp
- loading verification
- tracking number

## 10. Transfer
- source OUT
- transit optional
- target IN
- company total net stock unchanged
- partial send/receive olabilir
- transfer damage/shortage ayrı exception

## 11. Sayım
- count session
- frozen/snapshot quantity
- blind count optional
- first count
- recount
- discrepancy
- threshold approval
- final adjustment

Asla stock = counted_qty yapılmaz.
COUNT_ADJUSTMENT ledger movement oluşturulur.

## 12. Lot/Serial
Lot gereken üründe:
- source lot
- expiry
- quantity
izlenir.

Serial üründe her serial unique ve hareket bazında takip edilir.

## 13. Mobile/offline
- scan queue
- duplicate scan protection
- offline timestamp
- sync conflict
- retry
- device/user
izlenmelidir.

Offline kayıt server state ile çakışırsa sessiz overwrite yasak.

## 14. Depo KPI
- inventory accuracy
- pick accuracy
- dock-to-stock
- order cycle time
- OTIF
- lines/hour
- space utilization
- damage rate
- stockout rate

## 15. Edge-case
- aynı barcode iki ürüne bağlı
- lot süresi geçmiş
- target bin full
- partial pallet
- over-receipt
- shipment cancelled after loading
- transfer lost in transit
- duplicate scan
- negative inventory

## 16. Yasaklar
- direct stock update
- sevkten önce stock OUT
- sayımda geçmiş movement silme
- transferi sales/purchase sayma
- mismatch scan'i otomatik kabul

## 17. Definition of Done
Her fiziksel hareketin source document, location, status, qty, barcode/lot/serial ve ledger etkisi izlenebilir.

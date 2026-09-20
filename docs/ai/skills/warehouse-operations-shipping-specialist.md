# Skill: Depo Operasyon ve Sevkiyat Uzmanı

## Rol
Mal kabulden sevkiyata kadar fiziksel stok akışını gerçek depo operasyonuna uygun tasarlar.

## Kapsam
- mal kabul
- kalite/karantina
- put-away
- lokasyon
- rezervasyon
- picking
- packing
- koli/palet
- transfer
- transit
- sayım
- cycle count
- sevkiyat
- iade
- barkod
- mobil depo

## Zorunlu kontroller
- source/target warehouse-location
- stock status: available/quarantine/rework/scrap/transit
- lot/serial/batch
- expiry/FEFO
- FIFO gerektiği yer
- UOM conversion
- reservation
- available-to-promise
- picking strategy
- partial pick/ship
- over-pick prevention
- scan validation
- package hierarchy
- carrier/shipping label
- handoff time
- damage/shortage/overage

## Sayım
- blind count opsiyonu
- first/second count
- discrepancy threshold
- approval
- ledger COUNT_ADJUSTMENT
- historical movements silinmez

## Transfer
- source OUT
- transit opsiyonel
- target IN
- şirket toplam stoğu net değişmez
- partial receipt desteklenebilir

## KPI
- pick accuracy
- order cycle time
- dock-to-stock
- inventory accuracy
- OTIF
- lines/hour
- space utilization

## Yasaklar
- ürün kartındaki stock alanını elle set etmek
- sevkiyat post edilmeden stok düşürmek
- tarama uyuşmazlığını sessiz kabul etmek
- transferi satış/alış saymak

## Definition of Done
Fiziksel hareket, lokasyon/status, rezervasyon, belge ve exception akışı net.

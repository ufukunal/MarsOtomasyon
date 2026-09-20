# Skill: İstatistik ve Analiz Uzmanı

## 1. Misyon
KPI, dashboard, rapor ve analitik sonuçların matematiksel olarak doğru, iş tanımı net ve yanıltıcı olmayan biçimde üretilmesini sağlar.

## 2. Metrik sözleşmesi
Her KPI için:
- business name
- technical name
- purpose
- formula
- numerator
- denominator
- grain
- time dimension
- filters
- excluded statuses
- currency
- source
- refresh cadence
- owner
tanımlanır.

## 3. Grain
Önce veri seviyesi belirlenir:
- order
- order line
- shipment
- invoice line
- customer
- product
- warehouse
- day/month

Farklı grain tablolar yanlış join edilerek duplicate ölçüm üretmemelidir.

## 4. Count kuralları
- COUNT(*)
- COUNT DISTINCT
- active entity
- completed order
- cancelled excluded?
açık tanımlanır.

## 5. Average
Mean her zaman doğru değildir.
Değerlendir:
- median
- weighted average
- trimmed mean
- percentile

Özellikle:
- order value
- lead time
- delivery time
outlier etkisi incelenir.

## 6. Rate
Rate için pay/payda aynı population ve dönemden gelmelidir.
Örnek:
conversion = completed orders / eligible opportunities
Tanım değişmeden raporda oran değiştirilemez.

## 7. Time analysis
- calendar vs fiscal
- timezone
- daily/weekly/monthly
- seasonality
- rolling period
- YoY/MoM
- partial current period
ayrılır.

## 8. Currency
Farklı currencies toplarken:
- transaction currency
- base currency
- conversion rate/date
belirlenir.
Nominal değer ile sabit kur karşılaştırması karıştırılmaz.

## 9. Returns/cancellations
Revenue tanımında:
- cancelled
- returned
- refunded
- credit note
- partial return
etkisi açık olmalı.

## 10. Missing data
Eksik veri:
- zero değildir
- null olabilir
- unknown olabilir
- excluded olabilir
Kural rapor tanımında yazılır.

## 11. Outlier
Outlier:
- data error mı
- gerçek extreme değer mi
ayrılır.
Sessizce silinmez.

## 12. Segmentation
Gerektiğinde:
- customer segment
- channel
- product family
- warehouse
- region
- cohort
kullanılır.

## 13. KPI örnekleri
- revenue
- gross margin
- contribution margin
- order conversion
- fill rate
- OTIF
- inventory turnover
- stockout
- aging
- DSO
- DPO
- return rate
- warehouse productivity
- marketplace profitability

Her biri ayrı formula contract ister.

## 14. Görselleştirme
- trend -> line
- category compare -> bar
- composition -> pie/stack only when meaningful
- distribution -> histogram/box if supported
- relationship -> scatter
- KPI -> number + context

Chart axis ve scale yanıltıcı olmamalıdır.

## 15. İstatistiksel yorum
- correlation != causation
- small sample caution
- selection bias
- survivorship bias
- denominator drift
- Simpson's paradox
gibi riskler gerektiğinde belirtilir.

## 16. Tahmin/forecast
Forecast istenirse:
- historical window
- seasonality
- model
- confidence/uncertainty
- backtest
açık olmalıdır.
Tahmin gerçek sonuç gibi gösterilmez.

## 17. Yasaklar
- eksik veriyi sessiz 0 yapmak
- farklı grain join edip total şişirmek
- birim/currency karıştırmak
- partial period'i full period ile çıplak karşılaştırmak
- korelasyonu neden diye sunmak

## 18. Definition of Done
Metrik başka biri tarafından aynı kaynak ve formülle aynı sonucu üretecek kadar açık tanımlanmıştır.

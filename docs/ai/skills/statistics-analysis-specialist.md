# Skill: İstatistik ve Analiz Uzmanı

## Rol
KPI, rapor, dashboard ve karar destek göstergelerinin istatistiksel olarak doğru ve yeniden üretilebilir olmasını sağlar.

## Her metrik için zorunlu tanım
- iş adı
- teknik adı
- formül
- numerator
- denominator
- grain
- tarih boyutu
- para birimi
- filtreler
- excluded states
- source tables/read model
- refresh frequency
- owner

## Analiz kontrolleri
- count distinct gereksinimi
- duplicate source
- missing data
- outlier
- seasonality
- cohort
- segment
- weighted average
- median vs mean
- rate vs absolute
- nominal vs real
- cumulative vs period
- timezone/calendar
- returns/cancellations impact

## Yönetim KPI örnekleri
- revenue
- gross margin
- contribution margin
- order conversion
- fill rate
- OTIF
- inventory turnover
- stockout rate
- DSO/DPO
- aging
- warehouse productivity
- marketplace channel profitability

## Görselleştirme
- trend -> line
- kategori karşılaştırma -> bar
- composition -> sınırlı pie/stack
- relationship -> scatter
- dual-axis yalnız zorunluysa
- sıfırdan başlamayan eksende yanıltma riski açıklanır

## Yasaklar
- korelasyonu nedensellik diye sunmak
- eksik veriyi sessiz sıfır yapmak
- payda değişimini saklamak
- toplamla ortalamayı karıştırmak
- farklı dönemleri aynıymış gibi karşılaştırmak

## Definition of Done
Metrik tanımı ve veri grain'i başka biri tarafından aynı sonuçla yeniden üretilebilir.

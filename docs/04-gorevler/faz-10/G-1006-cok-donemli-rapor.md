# G-1006 — Çok dönemli rapor

## Amaç
Birden fazla period DB'yi ayrı sorgulayıp PHP'de birleştiren konsolide rapor altyapısını Faz 10'a tamamlamak.

## Önkoşul
G-1001, Master period access.

## Dokunulacak dosyalar
- MultiPeriodQuery
- PeriodRangeSelector
- consolidated report UI
- multi-db tests

## Şema / Kod
G-1112 mevcut sözleşmesi ve iş kuralı 54 birlikte kanoniktir.

## Kurallar
- reports.consolidated.
- Her DB ayrı sorgu.
- try/finally context restore.
- period metadata.
- archived/detached sessiz atlanmaz.
- Money BCMath.

## Kabul ölçütü
- 1/3+ period birleşiyor.
- Context hata sonrası geri dönüyor.
- Yetkisiz period engelleniyor.
- cost.view yine korunuyor.

## İstem
> G-1112 sözleşmesini Faz 10 rapor motoruna entegre ederek çok dönemli raporu uygula.

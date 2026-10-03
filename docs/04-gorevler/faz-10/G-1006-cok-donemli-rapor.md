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
Kanonik implementation sahibi **G-1006**'dır. Veri/iş kuralı kaynağı `docs/02-is-kurallari/54-export-ve-cok-donem.md` ve ortak reporting sözleşmesidir. G-1112 bu altyapıyı daha sonra Faz 11b dönem-devri bağlamında tekrar kurmadan doğrular/kullanır.

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
> MultiPeriodQuery, PeriodRangeSelector ve konsolide rapor entegrasyonunu Faz 10 ortak rapor motorunun kanonik implementation'ı olarak uygula. G-1112'ye bağımlılık oluşturma; G-1112 bu çıktıyı yeniden kullanacaktır.

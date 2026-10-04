# G-1005 — Export queue ve geçmiş

## Amaç
Büyük rapor/export işlerini queue ile üretmek, durum/progress/download geçmişini yönetmek.

## Önkoşul
G-1004.

## Dokunulacak dosyalar
- report_export_jobs migration/model
- queue job
- export center UI
- storage lifecycle
- tests

## Şema / Kod
queued|processing|done|failed.

## Kurallar
- Queue session PeriodContext'e güvenmez.
- company/period/report/filter/actor açık payload.
- Hassas tam rapor verisi metadata'ya yazılmaz.
- Dosya disk abstraction kullanır.

## Kabul ölçütü
- Queue farklı period context'te doğru raporu üretir.
- Progress/status güncellenir.
- Failed hata özeti taşır.
- Download yalnız yetkili kullanıcıya.

## İstem
> K-209/K-224 export queue ve geçmiş altyapısını uygula.

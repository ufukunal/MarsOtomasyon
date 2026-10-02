# G-1001 — Rapor çekirdeği

## Amaç
ReportDefinition/ReportQuery sözleşmesi, filtre/kolon/sort/total metadata ve permission kontrolünü kurmak.

## Önkoşul
K-202…K-207.

## Dokunulacak dosyalar
- reporting support classes
- report registry
- filter/column DTO
- permission integration
- core tests

## Şema / Kod
Kanonik kaynak: iş kuralı 52.

## Kurallar
- Rapor ikinci business truth oluşturmaz.
- cost.view query seviyesinde.
- created_at iş tarihi değildir.
- drill-down normal permission kullanır.

## Kabul ölçütü
- İki örnek rapor aynı sözleşmeyle çalışır.
- Yetkisiz cost kolonu SQL/select'e girmez.
- Filtre/sort/total deterministik.
- Invalid filter reddedilir.

## İstem
> Faz 10 ortak rapor motorunu K-202…K-207 kurallarına göre uygula.

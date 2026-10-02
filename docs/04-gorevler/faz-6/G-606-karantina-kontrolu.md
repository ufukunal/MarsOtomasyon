# G-606 — Karantina kontrolü

## Amaç

Satış iadesi karantinasını kısmi release/scrap kararlarıyla sonuçlandırmak.

## Önkoşul

G-603.

## Dokunulacak dosyalar

- QuarantineControl Livewire
- ReleaseQuarantine
- ScrapQuarantine
- ApplyPartialQuarantineDecision
- quarantine tests

## Şema / Kod

pending = quantity - released - scrapped.

Release:
- quarantine summary azalt
- stock movement yok

Scrap:
- quarantine summary azalt
- stock out reason=scrap

## Kurallar

- Pending quantity aşılmaz.
- Entry + balance lock edilir.
- Release moving average değiştirmez.
- Scrap çıkış maliyeti entry unit_cost snapshot'ı.
- Karar audit edilir.

## Kabul ölçütü

- 10 giriş => 7 release + 3 scrap.
- Release available artırıyor ama physical quantity değiştirmiyor.
- Scrap physical quantity azaltıyor.
- Concurrent karar pending'i aşmıyor.
- integrity:quarantine eşleşiyor.

## İstem

> K-102 ve iş kuralı 41'e göre parçalı karantina kararını uygula.

# G-1103 — Immutable deploy, release ve migration

## Amaç
Release dizini + atomik current geçişi, Master/period migration sırasını ve güvenli rollback sınırını uygulamak.

## Önkoşul
G-1102.

## Dokunulacak dosyalar
- deploy script/workflow
- release metadata
- migration runner
- deployment_runs

## Şema / Kod
Kanonik iş kuralı 58.

## Kurallar
- backup önce.
- Master migrate sonra tüm period migrate:periods.
- herhangi hata release'i active yapmaz.
- worker restart controlled.
- destructive down otomatik zorlanmaz.

## Kabul ölçütü
- başarısız period migration current symlink'i değiştirmiyor.
- başarılı release health sonrası active.
- previous release metadata var.
- deploy history oluşuyor.

## İstem
> K-239…K-241 immutable production deploy akışını uygula.

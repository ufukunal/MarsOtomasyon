# G-1109 — Canlı kabul ve disaster-recovery testleri

## Amaç
Production benzeri ortamda deploy, backup→restore, health, queue/scheduler, migration ve rollback davranışını uçtan uca doğrulamak.

## Önkoşul
G-1102…G-1108 ve Faz 11b.

## Dokunulacak dosyalar
- deployment acceptance tests
- backup/restore drill
- health smoke
- security verification checklist

## Şema / Kod
Yeni production şeması yok.

## Kurallar
- gerçek PostgreSQL Master + multi-period.
- production benzeri Valkey/queue.
- destructive production testi yok; isolated staging/recovery env.
- tüm sonuçlar audit/recovery artifact.

## Kabul ölçütü
- fresh deploy.
- failed migration stops release.
- backup recovery set verified.
- temporary restore integrity green.
- archive read-only.
- queue/scheduler health.
- secret/log checks.
- code rollback.
- go-live smoke.
- integrity:all green.

## İstem
> Faz 11 production readiness'i staging/recovery ortamında uçtan uca doğrula.
